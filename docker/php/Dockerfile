# Môi trường PHP dùng khi phát triển và chạy test (compose.yaml).
# Image chạy production nằm ở Dockerfile tại thư mục gốc.
FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath gd mbstring opcache pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY start-dev.sh /usr/local/bin/cinema-dev

# CLI tests retain their normal behavior; only the long-running web command enables OPcache.
RUN printf '%s\n' 'opcache.enable_cli=0' 'opcache.memory_consumption=128' \
    'opcache.max_accelerated_files=20000' > /usr/local/etc/php/conf.d/zz-cinemastar-dev.ini

WORKDIR /app
