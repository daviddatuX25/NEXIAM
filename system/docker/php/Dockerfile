FROM node:24-bookworm AS frontend

WORKDIR /app

COPY package*.json ./
RUN npm ci --ignore-scripts

COPY resources ./resources
COPY vite.config.ts tsconfig.json ./
RUN npm run build

# Composer official image may lack ext-sockets; ignore that platform req here.
# Final runtime image installs sockets below, then dump-autoload against that PHP.
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
# Keep require-dev in the application image for local Pest/Pint/PHPStan gates.
RUN composer install \
    --no-interaction \
    --prefer-dist \
    --no-progress \
    --no-scripts \
    --ignore-platform-req=ext-sockets

FROM php:8.4-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl libpq-dev libsqlite3-dev libzip-dev procps unzip \
    && docker-php-ext-install pcntl pdo_pgsql pdo_sqlite sockets zip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY --from=vendor /usr/bin/composer /usr/bin/composer
COPY --from=frontend /app/public/build ./public/build
COPY . .

# .env is dockerignored; seed a non-secret local file so artisan/pest boot
# cleanly inside the image. Compose environment overrides DB/mail at runtime.
RUN if [ ! -f .env ]; then cp .env.example .env; fi \
    && composer dump-autoload --optimize \
    && php artisan key:generate --force --no-interaction \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
