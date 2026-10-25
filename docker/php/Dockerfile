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
# pdo_mysql dipertahankan HANYA untuk `php artisan db:migrate-to-pgsql` (baca data lama
# dari MySQL sekali saat pindah ke Postgres) - koneksi aktif aplikasi selalu pgsql.

# Mitigasi CVE-2026-115560 (RCE lewat session upload progress, belum ada patch resmi PHP).
# Cabut setelah PHP merilis perbaikan.
RUN echo 'session.upload_progress.enabled=0' > /usr/local/etc/php/conf.d/zz-upload-progress.ini

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /app/vendor ./vendor
# bootstrap/cache/*.php dari host bisa berisi daftar package versi dev (mis. laravel/pail)
# yang tidak ikut ter-install di vendor --no-dev di atas - pakai yang di-regenerate ulang
# oleh `composer dump-autoload` di stage vendor supaya konsisten dengan isi vendor/ final.
COPY --from=vendor /app/bootstrap/cache ./bootstrap/cache
COPY --from=assets /app/public/build ./public/build

# public/ di-mount sebagai named volume saat runtime (dishare dengan container nginx -
# nginx dan PHP-FPM harus melihat file yang sama persis di path yang sama). Volume
# named tidak otomatis ter-refresh saat image di-rebuild, jadi simpan salinan hasil
# build ini di public-src sebagai sumber sinkronisasi tiap container start
# (lihat docker/php/entrypoint.sh).
RUN cp -r public public-src

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache public public-src

USER www-data

EXPOSE 9000
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
