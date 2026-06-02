#!/bin/bash
set -euo pipefail

# ============================================================
# CẤU HÌNH — chỉnh các biến này cho phù hợp với VPS của bạn
# ============================================================
VPS_HOST="192.168.10.252"
VPS_USER="tuanda"
VPS_PORT="22"
VPS_PATH="/home/tuanda/mobile_v2"       # đường dẫn project trên VPS
GIT_BRANCH="main"
SSH_KEY=""                        # để trống nếu dùng key mặc định (~/.ssh/id_rsa)
# ============================================================

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log()  { echo -e "${BLUE}[$(date '+%H:%M:%S')]${NC} $1"; }
ok()   { echo -e "${GREEN}[OK]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
fail() { echo -e "${RED}[FAIL]${NC} $1"; exit 1; }

SSH_OPTS="-p $VPS_PORT -o StrictHostKeyChecking=no"
[[ -n "$SSH_KEY" ]] && SSH_OPTS="$SSH_OPTS -i $SSH_KEY"

ssh_run() {
    ssh $SSH_OPTS "${VPS_USER}@${VPS_HOST}" "$1"
}

# ------------------------------------------------------------
# BƯỚC 1: Push code lên Git
# ------------------------------------------------------------
log "Bước 1/4 — Push code lên Git..."

CURRENT_BRANCH=$(git -C "$(dirname "$0")" rev-parse --abbrev-ref HEAD 2>/dev/null || echo "")
if [[ -z "$CURRENT_BRANCH" ]]; then
    warn "Không phát hiện git repo ở thư mục này. Bỏ qua bước push."
else
    # Bỏ qua mode-bit changes (do Docker chown gây ra trên VPS)
    git -C "$(dirname "$0")" config core.filemode false

    git -C "$(dirname "$0")" add -A
    if git -C "$(dirname "$0")" diff --cached --quiet; then
        warn "Không có thay đổi mới để commit."
    else
        git -C "$(dirname "$0")" commit -m "deploy: $(date '+%Y-%m-%d %H:%M:%S')"
        ok "Đã commit."
    fi

    # Đồng bộ với remote trước khi push (tránh non-fast-forward)
    git -C "$(dirname "$0")" fetch origin "$GIT_BRANCH"
    if ! git -C "$(dirname "$0")" pull --rebase origin "$GIT_BRANCH"; then
        warn "Rebase fail. Reset local về remote và bỏ commit local rác."
        git -C "$(dirname "$0")" rebase --abort 2>/dev/null || true
        git -C "$(dirname "$0")" reset --hard "origin/$GIT_BRANCH"
    fi

    git -C "$(dirname "$0")" push origin "$GIT_BRANCH"
    ok "Đã push branch '$GIT_BRANCH'."
fi

# ------------------------------------------------------------
# BƯỚC 2: VPS kéo code mới
# ------------------------------------------------------------
log "Bước 2/4 — VPS pull code từ Git..."

ssh_run "
    set -e
    cd '$VPS_PATH'

    # Backup các file env production trước khi reset
    TMP_BAK=\$(mktemp -d)
    [ -f react/.env ] && cp react/.env \"\$TMP_BAK/react.env\"
    [ -f laravel/.env ] && cp laravel/.env \"\$TMP_BAK/laravel.env\"

    # Chown storage + bootstrap/cache về user host (uid 1000) qua container nếu container đang chạy
    # để 'git reset --hard' không bị Permission denied trên các file .gitignore
    if docker compose ps laravel --status running 2>/dev/null | grep -q laravel; then
        docker compose exec -T laravel chown -R 1000:1000 storage bootstrap/cache 2>/dev/null || true
    fi

    git fetch origin
    git reset --hard origin/$GIT_BRANCH
    git clean -fd -e laravel/.env -e react/.env

    # Restore env files
    [ -f \"\$TMP_BAK/react.env\" ] && cp \"\$TMP_BAK/react.env\" react/.env
    [ -f \"\$TMP_BAK/laravel.env\" ] && cp \"\$TMP_BAK/laravel.env\" laravel/.env
    rm -rf \"\$TMP_BAK\"
"
ok "VPS đã pull code mới nhất (đã giữ env production)."

# ------------------------------------------------------------
# BƯỚC 3: Rebuild Docker containers
# ------------------------------------------------------------
log "Bước 3/4 — Rebuild và restart Docker containers..."

ssh_run "
    set -e
    cd '$VPS_PATH'

    # Đảm bảo file .env tồn tại
    if [ ! -f laravel/.env ]; then
        echo '[WARN] laravel/.env chưa tồn tại. Đang copy từ .env.example...'
        cp laravel/.env.example laravel/.env
    fi

    docker compose pull --quiet 2>/dev/null || true
    docker compose up -d --build --remove-orphans

    # Restart nginx để clear FastCGI upstream IP cache trỏ đến container laravel (IP đổi mỗi lần recreate)
    docker compose restart nginx
"
ok "Containers đã được rebuild và chạy (đã restart nginx để refresh upstream)."

# ------------------------------------------------------------
# BƯỚC 4: Chạy các lệnh Laravel
# ------------------------------------------------------------
log "Bước 4/4 — Chạy artisan commands trong container..."

ssh_run "
    set -e
    cd '$VPS_PATH'

    echo '→ Đợi MySQL ready...'
    for i in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15; do
        if docker compose exec -T mysql mysqladmin ping -h localhost --silent 2>/dev/null; then
            echo \"MySQL ready sau \${i} lần thử\"
            break
        fi
        sleep 2
    done

    echo '→ Migrate database...'
    docker compose exec -T laravel php artisan migrate --force

    echo '→ Tạo storage link...'
    docker compose exec -T laravel php artisan storage:link 2>/dev/null || true

    echo '→ Clear cache cũ...'
    docker compose exec -T laravel php artisan cache:clear
    docker compose exec -T laravel php artisan config:clear
    docker compose exec -T laravel php artisan route:clear
    docker compose exec -T laravel php artisan view:clear

    echo '→ Build cache production...'
    docker compose exec -T laravel php artisan config:cache
    docker compose exec -T laravel php artisan route:cache
    docker compose exec -T laravel php artisan view:cache

    echo '→ Sửa quyền storage...'
    docker compose exec -T laravel chown -R www-data:www-data storage bootstrap/cache
    docker compose exec -T laravel chmod -R 775 storage bootstrap/cache
"
ok "Artisan commands hoàn tất."

# ------------------------------------------------------------
# KIỂM TRA NHANH
# ------------------------------------------------------------
log "Restart nginx_proxy_manager để fix cache IP upstream..."
ssh_run "docker restart nginx_proxy_manager >/dev/null 2>&1 && sleep 3 || true"
ok "NPM restarted."

log "Kiểm tra trạng thái containers..."
ssh_run "cd '$VPS_PATH' && docker compose ps"

echo ""
echo -e "${GREEN}✓ Deploy thành công!${NC}"
echo -e "  Laravel API : http://${VPS_HOST}:8080"
echo -e "  phpMyAdmin  : http://${VPS_HOST}:8081"
echo -e "  Frontend    : http://${VPS_HOST}:5173"
