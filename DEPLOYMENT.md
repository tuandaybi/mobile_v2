# Hướng dẫn deploy dự án Mobile

## 1. Thành phần cần deploy

Dự án gồm:
- Frontend React build tĩnh
- Backend Laravel
- Database
- Storage/public files
- Có thể dùng Docker hoặc deploy thủ công

---

## 2. Phương án deploy phổ biến

### Phương án A: Docker
Phù hợp khi muốn đồng bộ môi trường.

```sh
cd Mobile
docker compose up -d --build
```

Dừng:

```sh
docker compose down
```

### Phương án B: Deploy thủ công
Phù hợp khi có sẵn VPS với Nginx/Apache/PHP.

---

## 3. Deploy backend Laravel

Vào thư mục backend:

```sh
cd Mobile/laravel
```

Cài dependency production:

```sh
composer install --no-dev --optimize-autoloader
```

Tạo file môi trường:

```sh
copy .env.example .env
```

Thiết lập:
- APP_ENV
- APP_DEBUG=false
- APP_URL
- DB_*
- MAIL_*
- cache/session/queue nếu dùng

Sinh key:

```sh
php artisan key:generate
```

Chạy migrate:

```sh
php artisan migrate --force
```

Tạo symbolic link storage:

```sh
php artisan storage:link
```

Tối ưu cache:

```sh
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Nếu có queue:

```sh
php artisan queue:work
```

---

## 4. Deploy frontend React

Vào thư mục frontend:

```sh
cd Mobile/react
npm install
npm run build
```

Sau build, thư mục output thường là `dist/`.

Cần:
- copy `dist/` lên web server
- hoặc cấu hình reverse proxy để serve frontend

Nếu frontend và backend khác domain/subdomain:
- cần kiểm tra CORS
- cần cấu hình base URL API đúng

---

## 5. Database

Trước khi deploy production:
- backup database cũ
- kiểm tra migration mới
- test migration ở staging nếu có

---

## 6. File upload / storage

Laravel dùng `storage/app/public`, nên cần:

```sh
php artisan storage:link
```

Đảm bảo web server có quyền ghi với thư mục:
- `storage/`
- `bootstrap/cache/`

---

## 7. Checklist production

- `APP_DEBUG=false`
- cấu hình DB đúng
- storage writable
- `storage:link` đã chạy
- frontend build đúng API URL
- backup DB trước migrate
- kiểm tra login, dashboard, API chính

---

## 8. Gợi ý cấu hình web server

### Backend Laravel
Trỏ document root tới:

```text
Mobile/laravel/public
```

### Frontend React
Serve từ thư mục build:

```text
Mobile/react/dist
```

Hoặc cho frontend build rồi reverse proxy về backend API.

---

## 9. Sau deploy nên kiểm tra

- Trang login frontend
- API auth
- Các trang dashboard / báo cáo / danh mục
- Upload file nếu có
- App update API nếu đang dùng
- Log lỗi Laravel trong `storage/logs/`

---

## 10. Ghi chú

- Nếu dùng Docker, nên có file `.env` riêng cho production
- Nếu deploy thủ công, nên dùng process manager và web server chuẩn
- Nên có môi trường staging trước production