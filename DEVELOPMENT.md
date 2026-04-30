# Hướng dẫn phát triển dự án Mobile

## Yêu cầu môi trường

### Frontend
- Node.js 18+
- npm

### Backend
- PHP phù hợp với phiên bản Laravel đang dùng
- Composer
- Database MySQL/MariaDB/PostgreSQL tùy `.env`

### Khuyến nghị thêm
- Docker Desktop
- Git
- VS Code / PhpStorm

---

## 1. Chạy backend Laravel

```sh
cd Mobile/laravel
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Nếu cần seed:

```sh
php artisan db:seed
```

Nếu cần link storage:

```sh
php artisan storage:link
```

---

## 2. Chạy frontend React

```sh
cd Mobile/react
npm install
npm run dev
```

Build:

```sh
npm run build
```

Lint:

```sh
npm run lint
```

---

## 3. Quy ước làm việc đề xuất

### Frontend
- Trang đặt trong `src/pages/`
- Component tái sử dụng đặt trong `src/components/`
- Kiểu dữ liệu đặt trong `src/types/`
- Logic route đặt trong `src/router/`
- CSS dùng chung đặt trong `src/styles/`

### Backend
- API route đặt trong `routes/api.php`
- Validate bằng Form Request nếu có thể
- Response dùng Resource nếu trả JSON list/detail
- Không nhồi toàn bộ business logic vào Controller

---

## 4. Quy trình thêm tính năng mới

### Nếu thêm màn hình mới
1. Tạo page trong `react/src/pages/`
2. Thêm route trong `react/src/router/`
3. Tạo component hỗ trợ nếu cần
4. Gọi API backend
5. Test luồng UI + API

### Nếu thêm API mới
1. Tạo route trong `laravel/routes/api.php`
2. Tạo controller/action xử lý
3. Tạo request validation nếu cần
4. Tạo resource trả dữ liệu nếu cần
5. Viết test feature cho endpoint quan trọng

---

## 5. Database

Khi đổi schema:

```sh
cd Mobile/laravel
php artisan make:migration ten_migration
php artisan migrate
```

Nếu cần dữ liệu mẫu:

```sh
php artisan make:seeder TenSeeder
php artisan db:seed
```

---

## 6. Tài liệu API

OpenAPI hiện nằm tại:

```text
Mobile/laravel/docs/openapi.yaml
```

Khi thêm endpoint mới, nên cập nhật tài liệu đồng thời.

---

## 7. Checklist trước khi commit

- Frontend build được
- Frontend không lỗi lint nghiêm trọng
- Backend migrate chạy ổn
- API mới test được bằng Postman hoặc test code
- Không commit `.env`, secret, token
- Không commit file build thừa nếu không cần

---

## 8. Một số lệnh hữu ích

### Laravel

```sh
php artisan route:list
```

```sh
php artisan test
```

### React

```sh
npm run dev
```

```sh
npm run build
```

```sh
npm run lint
```

---

## 9. Gợi ý cải thiện lâu dài

- Thêm service layer cho frontend API
- Chuẩn hóa cấu trúc response backend
- Thêm unit/feature test cho module quan trọng
- Viết module docs riêng cho auth, debt, mobile, service