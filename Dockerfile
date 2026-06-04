FROM php:8.3-fpm-alpine AS base

# Dependencias del sistema
RUN apk add --no-cache \
    postgresql-client \
    libpq-dev \
    libzip-dev \
    libxml2-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    oniguruma-dev \
    zip \
    unzip \
    git \
    curl \
    bash

# Extensiones PHP necesarias
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        zip \
        soap \
        dom \
        gd \
        bcmath \
        intl \
        mbstring \
        opcache

# Extensión Redis (phpredis) via PECL
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Instalar dependencias PHP (sin scripts para que no falle sin APP_KEY)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --ignore-platform-reqs

# Copiar código fuente
COPY . .

# Autoload optimizado
RUN composer dump-autoload --optimize

# Permisos de storage y cache
RUN mkdir -p storage/app/public \
              storage/framework/sessions \
              storage/framework/views \
              storage/framework/cache/data \
              storage/logs \
              storage/certificates \
              bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Script de arranque
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 8000

CMD ["/start.sh"]
