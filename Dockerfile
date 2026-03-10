FROM php:8.2-fpm

# Cai extension PHP + Node.js + MySQL client
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

# Cai Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set thu muc lam viec
WORKDIR /var/www/html

# Copy code Laravel vao container
COPY ./laravel /var/www/html
COPY ./docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# Cai thu vien PHP cho Laravel
RUN composer install --no-interaction --prefer-dist

# Copy file .env
COPY ./laravel/.env /var/www/html

# Set quyen de Laravel ghi cache, logs
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
