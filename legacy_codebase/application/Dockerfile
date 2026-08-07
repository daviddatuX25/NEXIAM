# Stage 1: Build static assets
FROM node:20-alpine AS assets-builder
WORKDIR /app
COPY package*.json ./
RUN npm ci --legacy-peer-deps
COPY . .
RUN npm run build

# Stage 2: Production runtime environment
FROM serversideup/php:8.4-fpm-nginx

# Switch to root to install system dependencies (Chromium, LibreOffice, Node.js)
USER root

# Install PHP gd extension
RUN install-php-extensions gd

# Tell Puppeteer to skip downloading Chromium (we use the system chromium instead)
ENV PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true

# Install dependencies:
# - chromium: for Spatie Browsershot (PDF generation)
# - libreoffice: for converting DOCX templates to PDF
# - poppler-utils: provides pdfunite for merging PDF files
# - nodejs & npm: for executing Puppeteer scripts at runtime
RUN apt-get update && apt-get install -y --no-install-recommends \
    chromium \
    libreoffice \
    poppler-utils \
    curl \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Switch back to www-data unprivileged user for security
USER www-data

# Set working directory
WORKDIR /var/www/html

# Copy the entire project code (chowned to www-data)
COPY --chown=www-data:www-data . .

# Copy compiled assets from Stage 1
COPY --from=assets-builder --chown=www-data:www-data /app/public/build ./public/build

# Install production Composer dependencies
RUN composer install --no-dev --optimize-autoloader --no-progress

# Install production npm dependencies (specifically puppeteer for Browsershot)
RUN PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true npm install --omit=dev --no-audit --no-fund --legacy-peer-deps

# Set environment variables for production performance and task automation
ENV PHP_OPCACHE_ENABLE=1
ENV AUTORUN_ENABLED=true
# Run migrations automatically on container boot
ENV AUTORUN_LARAVEL_MIGRATION=true
ENV AUTORUN_LARAVEL_MIGRATION_FORCE=true
# Seed default data (roles, courses, rooms, super admin, etc.) on first boot
ENV AUTORUN_LARAVEL_MIGRATION_SEED=true
# Create the storage symlink (public/storage -> storage/app/public)
ENV AUTORUN_LARAVEL_STORAGE_LINK=true
# Clear and rebuild config cache on each boot for clean production state
ENV AUTORUN_LARAVEL_CONFIG_CLEAR=true
ENV AUTORUN_LARAVEL_CACHE=true
