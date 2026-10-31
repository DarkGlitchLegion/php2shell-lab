FROM composer:2 AS composer-bin

FROM php:8.4-cli-bookworm

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libonig-dev \
    && docker-php-ext-install mbstring \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts

COPY . .
RUN php artisan package:discover --ansi \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]
