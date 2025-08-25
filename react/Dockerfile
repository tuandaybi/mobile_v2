# Sử dụng Node.js nhẹ
FROM node:20-alpine

# Thư mục làm việc trong container
WORKDIR /app

# Copy package.json và package-lock.json trước
COPY package*.json ./

# Cài dependencies
RUN npm install

# Mở port cho Vite
EXPOSE 5173

# Chạy Vite dev server với --host để Docker truy cập được
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0", "--watch"]
