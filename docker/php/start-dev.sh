#!/bin/sh
set -eu

cd /app
mkdir -p /var/cache/cinemastar/views /var/cache/cinemastar/bootstrap

# The named volume starts empty. Reinstall only when the lock file changes;
# composer uses the existing volume for subsequent restarts and offline demos.
lock_hash=$(sha256sum composer.json composer.lock | sha256sum | cut -d ' ' -f 1)
installed_hash=$(cat vendor/.cinemastar-lock 2>/dev/null || true)
if [ ! -f vendor/autoload.php ] || [ "$lock_hash" != "$installed_hash" ]; then
    composer install --prefer-dist --no-interaction --no-progress
    printf '%s\n' "$lock_hash" > vendor/.cinemastar-lock
fi

# Validate source timestamps on every request, so editing code still takes effect
# immediately. Dependency and compiled-view metadata now live on the Linux disk.
cd public
exec php -d opcache.enable_cli=1 -d opcache.validate_timestamps=1 \
    -d opcache.revalidate_freq=0 -S 0.0.0.0:8000 \
    ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
