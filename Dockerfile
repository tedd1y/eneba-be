# syntax=docker/dockerfile:1

# eneba-be production image: PHP-FPM and Nginx in one container, for Render's Docker runtime.

FROM composer:2 AS composer

FROM php:8.5-fpm-alpine

WORKDIR /var/www/html

# Nginx, plus the PHP extensions the app needs: pdo_pgsql for Postgres, intl for money formatting
# (Number::currency). OPcache is built into PHP 8.5. Build-only packages are removed afterwards.
RUN apk add --no-cache nginx icu-libs libpq \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev postgresql-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl \
    && apk del .build-deps \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-app.ini"
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zzz-app.conf
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/site.conf.template /etc/nginx/site.conf.template
COPY docker/start.sh /usr/local/bin/start

# Install dependencies first so they are cached until composer.json/composer.lock change.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
RUN composer install --no-dev --optimize-autoloader --prefer-dist --no-interaction --no-progress \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /usr/local/bin/start

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    LOG_LEVEL=info \
    PORT=10000

EXPOSE 10000

CMD ["start"]
