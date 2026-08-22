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
       libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pcntl pdo_pgsql pdo_sqlite sockets zip gd exif \
    && apt-get install -y --no-install-recommends libreoffice-writer-nogui poppler-utils \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY --from=vendor /usr/bin/composer /usr/bin/composer
COPY --from=frontend /app/public/build ./public/build
COPY . .

# .env is dockerignored; seed the runtime key into the local server
# environment so Laravel's built-in server and queue workers share it.
# `php artisan serve` spawns the built-in server with a STRIPPED environment
# (compose env vars never reach the web runtime), so the OMR worker transport
# vars must also be baked into .env — otherwise every worker call from the
# web runtime fails with "signing secret is invalid".
ARG APP_KEY
ARG OMR_WORKER_SIGNING_SECRET=nexiam-local-omr-worker-signing-secret-change-me
RUN if [ ! -f .env ]; then cp .env.example .env; fi \
    && if [ -n "$APP_KEY" ]; then sed -i "s|^APP_KEY=.*|APP_KEY=$APP_KEY|" .env; else php artisan key:generate --force --no-interaction; fi \
    && grep -q '^OMR_WORKER_SIGNING_SECRET=' .env || printf '%s\n' \
        'OMR_WORKER_ENABLED=true' \
        'OMR_WORKER_ENDPOINT=http://inference:8100' \
        "OMR_WORKER_SIGNING_SECRET=$OMR_WORKER_SIGNING_SECRET" \
        'OMR_WORKER_CONTRACT_VERSION=nexiam.omr.detection.v2' \
        'OMR_WORKER_ENGINE_NAME=OMRChecker' \
        'OMR_WORKER_ENGINE_COMMIT=de3f6982822895491be760325fd43e65e90fa293' \
        'OMR_WORKER_PATCH_IDENTITY=nexiam-headless-overlay-v1' >> .env \
    && composer dump-autoload --optimize \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

# Scan uploads: raise PHP upload/post limits (defaults reject >2M files).
COPY docker/php/conf.d/uploads.ini /usr/local/etc/php/conf.d/99-uploads.ini

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
