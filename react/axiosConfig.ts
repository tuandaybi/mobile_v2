import { message } from 'antd';
import axios from 'axios';

const api = axios.create({
  baseURL: '/api',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
});

// Gắn token từ localStorage vào mọi request nếu có
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    config.headers = config.headers || {};
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
      const msg = error.response.data?.message || '';
      if (status === 401) {
        if (msg === 'Token không tồn tại' || msg === 'Token không hợp lệ' || msg === 'Token đã hết hạn') {
          localStorage.removeItem('auth_token');
          localStorage.removeItem('user');
          window.location.href = '/login';
        }
      }
    }
    if (!error.config?.suppressToast) {
      message.error(error.response?.data?.message || 'Có lỗi xảy ra');
    }
    return Promise.reject(error);
  }
);


export default api;
