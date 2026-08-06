# =========================================================
# Stage 1: Build frontend menggunakan Node.js 22
# =========================================================
FROM node:22-bookworm AS frontend-builder

WORKDIR /app

COPY package.json package-lock.json ./

RUN node --version \
    && npm --version \
    && npm ci --no-audit --no-fund

COPY . .

RUN npm run build


# =========================================================
# Stage 2: Laravel application
# =========================================================
FROM php:8.4-cli

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update && apt-get install -y \
    ca-certificates \
    curl \
    git \
    libfreetype6-dev \
    libicu-dev \
    libjpeg62-turbo-dev \
    libonig-dev \
    libpng-dev \
    libpq-dev \
    libwebp-dev \
    libxml2-dev \
    libzip-dev \
    unzip \
    zip \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install \
        dom \
        gd \
        intl \
        mbstring \
        pdo_pgsql \
        pgsql \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN git config --global --add safe.directory /var/www/html

RUN mkdir -p \
    storage/framework/views \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/logs \
    bootstrap/cache

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

COPY . .

# Ambil hasil build Vite dari frontend-builder.
COPY --from=frontend-builder /app/public/build ./public/build

RUN mkdir -p \
    storage/framework/views \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/logs \
    bootstrap/cache \
    && composer dump-autoload \
        --no-dev \
        --optimize \
        --no-interaction \
    && php artisan storage:link || true

RUN echo "upload_max_filesize=512M" \
        > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size=520M" \
        >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit=600M" \
        >> /usr/local/etc/php/conf.d/uploads.ini

EXPOSE 8000

CMD [
    "php",
    "artisan",
    "serve",
    "--host=0.0.0.0",
    "--port=8000"
]