#!/bin/sh
set -eu
export PORT="${PORT:-10000}"
case "$PORT" in ''|*[!0-9]*) echo 'PORT must be numeric' >&2; exit 1;; esac
if [ "$PORT" -lt 1 ] || [ "$PORT" -gt 65535 ]; then echo 'PORT is out of range' >&2; exit 1; fi
mkdir -p storage/app/public storage/app/private storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
php artisan config:clear
php deploy/preflight.php
php artisan migrate --force
php artisan cinema:publish-catalog --no-interaction
# Never seed automatically: demo seeders contain fixed, publicly documented passwords.
php artisan storage:link --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
. ./deploy/workers.sh
printf 'Listen %s\n' "$PORT" > /etc/apache2/ports.conf
exec apache2-foreground
