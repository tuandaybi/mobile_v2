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

## Ghi chú

- README mặc định trong `laravel/` hiện còn là README gốc của Laravel, nên ưu tiên đọc tài liệu ở thư mục `Mobile/`.
- OpenAPI backend nằm tại:
  - `Mobile/laravel/docs/openapi.yaml`