FROM node:22-bookworm-slim AS node

FROM php:8.4-fpm-bookworm AS php-base

WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
    && apt-get install --yes --no-install-recommends \
        gosu \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd mbstring pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-scripts \
    --optimize-autoloader

RUN mkdir -p \
    bootstrap/cache \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views

FROM php-base AS frontend

COPY --from=node /usr/local /usr/local
COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN php artisan wayfinder:generate --with-form --no-interaction \
    && npm run build

FROM php-base AS app

COPY . .
COPY --from=frontend /var/www/html/public/build ./public/build
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint

RUN rm -f public/hot \
    && mkdir -p \
        bootstrap/cache \
        storage/app/private/kompen-respon-hub/imports \
    && chown -R www-data:www-data bootstrap/cache storage \
    && chmod +x /usr/local/bin/docker-entrypoint \
    && { \
        echo 'upload_max_filesize=25M'; \
        echo 'post_max_size=25M'; \
        echo 'max_execution_time=600'; \
        echo 'max_input_time=600'; \
        echo 'memory_limit=512M'; \
    } > /usr/local/etc/php/conf.d/sikompen.ini

ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]

FROM nginx:1.27-alpine AS web

WORKDIR /var/www/html

COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public ./public
