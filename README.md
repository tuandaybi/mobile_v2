# Mobile

Dự án Mobile gồm 2 phần chính:

- `react/`: frontend quản trị, dashboard, báo cáo, auth
- `laravel/`: backend API, auth, database, tài liệu OpenAPI
- `docker/`, `Dockerfile`, `docker-compose.yml`: phục vụ môi trường chạy container
- `application/`: thư mục nghiệp vụ/bổ sung của dự án

## Cấu trúc thư mục

- `Mobile/react`
  - React + TypeScript + Vite
  - UI dùng Ant Design
  - Router, pages, components, store
- `Mobile/laravel`
  - Laravel API/backend
  - migrations, seeders, models, controllers, resources
  - `docs/openapi.yaml`

## Công nghệ sử dụng

### Frontend
- React 18
- TypeScript
- Vite
- Ant Design
- Zustand
- React Router

### Backend
- Laravel
- PHP
- MySQL/MariaDB hoặc database cấu hình qua `.env`
- Sanctum
- Spatie Permission

## Chạy dự án local

### 1. Backend Laravel

Vào thư mục backend:

```sh
cd Mobile/laravel
```

Cài dependency:

```sh
composer install
```

Tạo file môi trường:

```sh
copy .env.example .env
```

Sinh app key:

```sh
php artisan key:generate
```

Chạy migrate:

```sh
php artisan migrate
```

Nếu cần seed dữ liệu:

```sh
php artisan db:seed
```

Chạy backend:

```sh
php artisan serve
```

Backend mặc định thường chạy tại:

```text
http://127.0.0.1:8000
```

### 2. Frontend React

Vào thư mục frontend:

```sh
cd Mobile/react
```

Cài dependency:

```sh
npm install
```

Chạy dev server:

```sh
npm run dev
```

Build production:

```sh
npm run build
```

## Chạy bằng Docker

Tại thư mục `Mobile/`:

```sh
docker compose up -d --build
```

Dừng container:

```sh
docker compose down
```

## Tài liệu nên đọc tiếp

- `Mobile/DEVELOPMENT.md`
- `Mobile/DEPLOYMENT.md`
- `Mobile/ARCHITECTURE.md`

## Deploy lên VPS

### Yêu cầu VPS

- Ubuntu 20.04+
- Docker Engine >= 20.x, Docker Compose v2
- Git đã cấu hình SSH key với GitHub

### Docker Services

| Service | Container | Port | Mô tả |
|---------|-----------|------|-------|
| laravel | `laravel` | 9000 (internal) | PHP 8.2 FPM backend |
| react | `react` | 5173 | Vite dev server |
| nginx | `laravel_nginx` | 8080 | Reverse proxy → php-fpm |
| mysql | `laravel_mysql` | 3306 | MySQL 8.0 |
| phpmyadmin | `laravel_phpmyadmin` | 8081 | DB admin UI |

### Deploy lần đầu

```bash
# 1. Clone repo
cd /home/<user>
git clone <repo-url> mobile_v2
cd mobile_v2

# 2. Cấu hình .env
cp laravel/.env.example laravel/.env
nano laravel/.env
```

Các biến quan trọng trong `.env`:

```env
APP_URL=http://<domain-or-ip>:8080
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=mobile
DB_USERNAME=admin
DB_PASSWORD=admin
```

```bash
# 3. Build và chạy Docker
docker compose up -d --build

# Đợi tất cả container Up
docker compose ps

# 4. Setup Laravel
docker compose exec laravel php artisan key:generate
docker compose exec laravel php artisan migrate
docker compose exec laravel php artisan storage:link

# Phân quyền thư mục
docker compose exec laravel chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
docker compose exec laravel chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
```

### Cập nhật code (deploy hàng ngày)

```bash
cd /home/<user>/mobile_v2

# Pull code mới
git pull

# Nếu có migration mới
docker compose exec laravel php artisan migrate

# Nếu đổi Dockerfile hoặc package
docker compose up -d --build

# Clear cache (nếu cần)
docker compose exec laravel php artisan cache:clear
docker compose exec laravel php artisan config:clear
docker compose exec laravel php artisan route:clear
```

### Hoặc dùng deploy.sh

Sửa config đầu file `deploy.sh`:

```bash
VPS_HOST="your-vps-ip"
VPS_USER="root"
VPS_PORT="22"
VPS_PATH="/home/<user>/mobile_v2"
GIT_BRANCH="main"
```

Chạy: `chmod +x deploy.sh && ./deploy.sh`

Script sẽ: commit + push local → SSH pull trên VPS → rebuild Docker → migrate + clear cache.

### Các lệnh thường dùng

```bash
# Xem log Laravel
docker compose exec laravel tail -f /var/www/html/storage/logs/laravel.log

# Xem log container
docker compose logs -f laravel
docker compose logs -f react

# Vào shell container
docker compose exec laravel bash
docker compose exec react sh

# Restart tất cả
docker compose restart

# Rebuild 1 service
docker compose up -d --build laravel

# Backup DB
docker compose exec mysql mysqldump -u admin -padmin mobile > backup.sql

# Restore DB
docker compose exec -T mysql mysql -u admin -padmin mobile < backup.sql
```

### Truy cập

| Dịch vụ | URL |
|---------|-----|
| App (Frontend) | `http://<ip>:5173` |
| API (Backend) | `http://<ip>:8080/api` |
| phpMyAdmin | `http://<ip>:8081` |

### Lưu ý quan trọng

- React đang chạy **dev server** (Vite), chưa build production.
- MySQL data lưu trong Docker volume `mysql_data` — **không xóa volume** nếu không muốn mất data.
- File upload max **500MB** (config trong `docker/php/uploads.ini` và `docker/nginx/conf.d/default.conf`).

## Ghi chú

- README mặc định trong `laravel/` hiện còn là README gốc của Laravel, nên ưu tiên đọc tài liệu ở thư mục `Mobile/`.
- OpenAPI backend nằm tại:
  - `Mobile/laravel/docs/openapi.yaml`