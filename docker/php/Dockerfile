# syntax=docker/dockerfile:1

# --- Stage 1: build frontend assets ---
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm install
COPY resources resources
COPY vite.config.js ./
RUN npm run build

# --- Stage 2: install PHP dependencies ---
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev

# --- Stage 3: runtime image ---
FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        libsodium-dev \
        postgresql-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install pdo_pgsql pdo_mysql mbstring bcmath zip intl sodium opcache \
    && apk del $PHPIZE_DEPS \
    && rm -rf /var/cache/apk/*
# pdo_mysql is kept ONLY for `php artisan db:migrate-to-pgsql` (to read old data
# from MySQL once when moving to Postgres) - the application's active connection is always pgsql.

# Mitigation for CVE-2026-115560 (RCE via session upload progress, no official PHP patch yet).
# Remove once PHP releases a fix.
RUN echo 'session.upload_progress.enabled=0' > /usr/local/etc/php/conf.d/zz-upload-progress.ini

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /app/vendor ./vendor
# bootstrap/cache/*.php from the host may contain a list of dev-version packages (e.g. laravel/pail)
# that are not installed in the vendor --no-dev above - use the one regenerated
# by `composer dump-autoload` in the vendor stage so it stays consistent with the final vendor/ contents.
COPY --from=vendor /app/bootstrap/cache ./bootstrap/cache
COPY --from=assets /app/public/build ./public/build

# public/ is mounted as a named volume at runtime (shared with the nginx container -
# nginx and PHP-FPM must see exactly the same files at the same path). A named
# volume is not refreshed automatically when the image is rebuilt, so keep a copy of this
# build output in public-src as the sync source on every container start
# (see docker/php/entrypoint.sh).
RUN cp -r public public-src

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache public public-src

USER www-data

EXPOSE 9000
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
