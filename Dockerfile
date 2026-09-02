# Build compiled Vite assets first.
FROM node:20-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

# Run Laravel with PHP 8.3, compatible with Laravel 11 and this project.
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
    } > /usr/local/etc/php/conf.d/zz-cinemastar-opcache.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

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
CMD ["sh","-c","set -e; php artisan migrate --force; if ! php -r 'require \"vendor/autoload.php\"; $app = require \"bootstrap/app.php\"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); exit(Illuminate\\Support\\Facades\\DB::table(\"users\")->exists() ? 0 : 1);'; then php artisan db:seed --force; fi; php artisan storage:link --force || true; php artisan optimize:clear; php artisan config:cache; php artisan route:cache; php artisan view:cache; php artisan serve --host=0.0.0.0 --port=\${PORT:-10000}"]
