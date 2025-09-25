import path from 'path'
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    watch: {
      usePolling: true,
      interval: 100 
    },
    proxy: {
      '/api': {
        target: 'http://192.168.10.10:8080',
        changeOrigin: true,
        secure: false,
      },
    },
    allowedHosts: [
      //'0977769666.click',
      'localhost',
    ],
  },
})