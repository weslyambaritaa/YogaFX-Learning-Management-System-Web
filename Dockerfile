FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    curl \
    nodejs \
    npm \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    && docker-php-ext-install pdo_pgsql pgsql zip intl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-scripts

RUN npm install
RUN npm run build

RUN php artisan storage:link || true

EXPOSE 8000

RUN echo "upload_max_filesize=512M" > /usr/local/etc/php/conf.d/uploads.ini \
 && echo "post_max_size=520M" >> /usr/local/etc/php/conf.d/uploads.ini \
 && echo "memory_limit=600M" >> /usr/local/etc/php/conf.d/uploads.ini

CMD ["php","artisan","serve","--host=0.0.0.0","--port=8000"]