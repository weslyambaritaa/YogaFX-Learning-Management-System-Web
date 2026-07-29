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
    nodejs \
    npm \
    unzip \
    zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install dom gd intl mbstring pdo_pgsql pgsql zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN git config --global --add safe.directory /var/www/html
RUN mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs bootstrap/cache

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-source --optimize-autoloader --no-scripts

COPY package.json package-lock.json ./
RUN echo "=== DISK USAGE SEBELUM NPM CI ===" && df -h && echo "=== MEMORY SEBELUM NPM CI ===" && free -h
RUN npm ci

COPY . .

RUN mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs bootstrap/cache
RUN npm run build
RUN php artisan storage:link || true

EXPOSE 8000

RUN echo "upload_max_filesize=512M" > /usr/local/etc/php/conf.d/uploads.ini \
 && echo "post_max_size=520M" >> /usr/local/etc/php/conf.d/uploads.ini \
 && echo "memory_limit=600M" >> /usr/local/etc/php/conf.d/uploads.ini

CMD ["php","artisan","serve","--host=0.0.0.0","--port=8000"]