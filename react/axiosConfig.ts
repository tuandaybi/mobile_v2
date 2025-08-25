import axios from 'axios';

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
});

// Gắn token từ localStorage vào mọi request nếu có
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Xử lý khi token hết hạn
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response) {
      const status = error.response.status;
      const message = error.response.data?.message || '';
      // Chỉ logout nếu token hết hạn hoặc chưa xác thực, không phải lỗi đăng nhập/đăng ký
      if (status === 401) {
        if (message === 'Token không tồn tại' || message === 'Token không hợp lệ' || message === 'Token đã hết hạn') {
          localStorage.removeItem('auth_token');
          localStorage.removeItem('user');
          window.location.href = '/login';
        }
      }
    }
    return Promise.reject(error);
  }
);


export default api;
