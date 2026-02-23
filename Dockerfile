# Railway Production Dockerfile for Saturn Platform
FROM php:8.5-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    nodejs \
    npm \
    git \
    curl \
    libpng-dev \
    libzip-dev \
    icu-dev \
    postgresql-dev \
    oniguruma-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    bash

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_pgsql \
    pgsql \
    zip \
    gd \
    bcmath \
    intl \
    opcache \
    pcntl

# Install Redis extension via PECL
RUN apk add --no-cache autoconf g++ make \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del autoconf g++ make

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for caching
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --no-scripts --no-autoloader --ignore-platform-reqs

# Copy package files
COPY package.json package-lock.json ./

# Install Node dependencies (production only for smaller image)
RUN npm ci --include=dev

# Copy frontend source files needed for build
COPY resources ./resources
COPY vite.config.ts postcss.config.cjs tsconfig*.json ./
COPY public ./public

# Copy all remaining application files
COPY . .

# Clear cached service providers (may reference dev-only packages like laravel-ray)
RUN rm -f bootstrap/cache/packages.php bootstrap/cache/services.php

# Complete composer install
RUN composer dump-autoload --optimize

# Build frontend assets (React/Vite)
RUN npm run build && \
    # Verify build artifacts exist
    test -f public/build/manifest.json && \
    echo "✓ Frontend assets built successfully" || \
    (echo "✗ Frontend build failed - manifest.json not found" && exit 1)

# Remove node_modules to reduce image size (build artifacts in public/build are kept)
RUN rm -rf node_modules

# Create required directories
RUN mkdir -p storage/logs storage/framework/sessions storage/framework/views storage/framework/cache/data bootstrap/cache \
    && chmod -R 777 storage bootstrap/cache

# Copy configs
COPY railway/nginx.conf /etc/nginx/http.d/default.conf
COPY railway/supervisord.conf /etc/supervisord.conf
COPY railway/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Expose port
EXPOSE 8080

# Start with entrypoint
CMD ["/entrypoint.sh"]
