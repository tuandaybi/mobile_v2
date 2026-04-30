# Kiến trúc dự án Mobile

## Tổng quan

Dự án Mobile được tách thành 2 lớp chính:

1. **Frontend**: React + TypeScript + Vite
2. **Backend**: Laravel API

Mục tiêu là tách biệt giao diện và nghiệp vụ để dễ mở rộng, bảo trì, test và deploy.

---

## 1. Frontend (`react/`)

### Vai trò
- Hiển thị giao diện quản trị
- Quản lý đăng nhập/đăng ký/gia hạn
- Quản lý danh sách máy, dịch vụ, công nợ, báo cáo
- Gọi API từ backend Laravel

### Cấu trúc chính
- `src/pages/`: các trang màn hình
- `src/components/`: component dùng lại
- `src/router/`: điều hướng và bảo vệ route
- `src/store/`: state management
- `src/types/`: khai báo kiểu dữ liệu
- `src/styles/`: CSS dùng chung

### Luồng cơ bản
- Người dùng mở frontend
- Router kiểm tra xác thực
- Page gọi API backend
- Backend trả dữ liệu JSON
- Frontend render UI

---

## 2. Backend (`laravel/`)

### Vai trò
- Cung cấp API cho frontend
- Xử lý auth, phân quyền, dữ liệu nghiệp vụ
- Quản lý database, migration, seed dữ liệu
- Cung cấp tài liệu OpenAPI

### Cấu trúc chính
- `app/Models/`: model dữ liệu
- `app/Http/Controllers/`: controller xử lý request
- `app/Http/Requests/`: validate request
- `app/Http/Resources/`: chuẩn hóa response JSON
- `routes/api.php`: route API
- `database/migrations/`: định nghĩa schema
- `database/seeders/`: dữ liệu mẫu ban đầu
- `docs/openapi.yaml`: tài liệu API

### Các nhóm nghiệp vụ đang thấy trong codebase
- Auth / User
- Customer
- Device / Color / Store
- Mobile in / out
- Service
- Debt / Debt payment
- Notification
- App update API

---

## 3. Giao tiếp giữa frontend và backend

Frontend gọi tới backend qua HTTP API.

Ví dụ luồng:
- Login form gửi request
- Backend xác thực tài khoản
- Backend trả token / user info
- Frontend lưu trạng thái đăng nhập
- Các page sau dùng token để gọi API tiếp

---

## 4. Phân tầng nên duy trì

### Frontend
- `pages` chỉ nên xử lý luồng giao diện
- `components` tái sử dụng UI
- `store` giữ state dùng chung
- gọi API nên gom thành service layer nếu chưa có

### Backend
- Controller mỏng
- Validate ở Request class
- Business logic nên tách sang Service/Action
- Response chuẩn qua Resource

---

## 5. Hướng mở rộng khuyến nghị

- Tạo thư mục `services` ở frontend để gom API calls
- Tách business logic lớn trong Laravel sang `Actions` hoặc `Services`
- Bổ sung test cho các API quan trọng
- Chuẩn hóa mã lỗi và response format
- Viết tài liệu endpoint theo module

---

## 6. Sơ đồ logic đơn giản

```text
Người dùng
   ↓
React Frontend
   ↓ HTTP/JSON
Laravel API
   ↓
Database / Storage / Notification / App Update
```

---

## 7. Điểm cần chú ý hiện tại

- README trong `laravel/` vẫn là nội dung mặc định của Laravel
- Dự án đã khá lớn, nên cần tài liệu tổng hợp ở cấp thư mục `Mobile/`
- Nên thống nhất cách đặt tên module giữa frontend và backend để dễ mapping