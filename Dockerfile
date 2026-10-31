FROM composer:2 AS composer-bin

FROM php:8.4-cli-bookworm AS app

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libonig-dev \
    && docker-php-ext-install mbstring \
    && printf '%s\n' 'max_execution_time=10' 'max_input_time=10' 'memory_limit=128M' 'post_max_size=1M' 'upload_max_filesize=1M' > /usr/local/etc/php/conf.d/lab-limits.ini \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts

COPY . .
# Host .env stays excluded; Laravel's PHPUnit bootstrap expects an env file to exist.
RUN rm -f bootstrap/cache/*.php \
    && touch .env \
    && php artisan package:discover --ansi \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && touch /.php2shell-lab-container \
    && chmod 0444 /.php2shell-lab-container \
    && chown -R www-data:www-data storage bootstrap/cache

FROM app AS test

RUN composer install --no-interaction --no-progress --prefer-dist --no-scripts \
    && rm -f bootstrap/cache/*.php \
    && php artisan package:discover --ansi

USER www-data
CMD ["php", "artisan", "test"]

FROM app AS runtime

USER www-data
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]
