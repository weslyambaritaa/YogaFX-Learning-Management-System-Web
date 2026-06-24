FROM php:8.3-cli

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

RUN composer install --no-dev --optimize-autoloader

RUN npm install
RUN npm run build

RUN php artisan storage:link || true

EXPOSE 8000

CMD ["php","artisan","serve","--host=0.0.0.0","--port=8000"]