FROM composer:2 AS composer
FROM php:8.4-fpm-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev libxml2-dev libzip-dev libsqlite3-dev unzip curl && docker-php-ext-install pdo_mysql pdo_sqlite mbstring dom xml zip pcntl && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www
COPY backend/ ./
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache && composer install --no-dev --no-interaction --prefer-dist && chown -R www-data:www-data storage bootstrap/cache
USER www-data
CMD ["php-fpm"]
