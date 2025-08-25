FROM php:8.2-fpm

# Cài extension PHP + Node.js + MySQL client
RUN apt-get update && apt-get install -y \
    bash \
    build-essential \
    libpng-dev \
    libzip-dev zip \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    unzip \
    git \
    curl \
    gnupg \
    default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Cài Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set thư mục làm việc
WORKDIR /var/www/html

# Copy code Laravel vào container
COPY ./laravel /var/www/html

# Cài thư viện PHP cho Laravel
RUN composer install --no-interaction --prefer-dist

# Copy file .env
COPY ./laravel/.env /var/www/html

# Set quyền để Laravel ghi cache, logs
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
