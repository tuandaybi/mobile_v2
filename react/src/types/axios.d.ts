import 'axios';
declare module 'axios' {
  interface AxiosRequestConfig {
    /** Tắt toast lỗi ở interceptor (tuỳ dự án) */
    suppressToast?: boolean;
  }
}