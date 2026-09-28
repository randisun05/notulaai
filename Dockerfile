# syntax=docker/dockerfile:1

# --- Stage 1: build frontend assets (Vite) ---
FROM node:20-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

# --- Stage 2: PHP-FPM app (composer deps + built assets baked in) ---
FROM php:8.3-fpm-alpine AS app

WORKDIR /var/www/html

RUN apk add --no-cache \
        git \
        unzip \
        libpng-dev \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
        freetype-dev \
        libjpeg-turbo-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        bcmath \
        exif \
        pcntl \
        gd \
        zip \
        intl \
    && apk del git unzip

# ffmpeg memecah rekaman panjang (audio/video) jadi potongan untuk ditranskrip
# (App\Services\Audio\AudioSplitter). Dipakai oleh container app dan queue.
RUN apk add --no-cache ffmpeg

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .
COPY --from=frontend /app/public/build ./public/build

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress \
    && php artisan storage:link \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

USER www-data

EXPOSE 9000

CMD ["php-fpm"]

# --- Stage 3: Nginx serving the exact assets baked into the app image ---
FROM nginx:alpine AS webserver

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
