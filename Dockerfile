# Build compiled Vite assets first.
FROM node:20-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

# Run Laravel with PHP 8.3, as required by Laravel 13.
FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath gd mbstring opcache pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

# Keep PHP bytecode in memory between requests. This is especially useful on
# Render's small free instance once the service has woken up.
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.revalidate_freq=0'; \
        echo 'upload_max_filesize=6M'; \
        echo 'post_max_size=7M'; \
        echo 'max_file_uploads=10'; \
    } > /usr/local/etc/php/conf.d/zz-cinemastar-opcache.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Avoid a network round-trip to MySQL for Laravel's application cache.
ENV CACHE_STORE=file

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts

COPY . .
COPY --from=frontend /app/public/build ./public/build

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 10000

# Render injects PORT automatically. The database schema is migrated on startup.
# Sample data is seeded only when the users table is still empty.
# Runtime caches are rebuilt after Render has injected all environment variables.
CMD ["sh", "deploy/start.sh"]
