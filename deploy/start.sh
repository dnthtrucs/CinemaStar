#!/bin/sh
set -eu

# Render injects environment variables before this script runs.
php artisan migrate --force
if ! php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); exit(Illuminate\Support\Facades\DB::table("users")->exists() ? 0 : 1);'; then
    php artisan db:seed --force
fi
php artisan storage:link --force || true
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

if [ "${RUN_SCHEDULER:-false}" = "true" ]; then
    echo 'Scheduler enabled: starting schedule:work inside the web container.'
    (
        while true; do
            scheduler_status=0
            php artisan schedule:work --no-interaction || scheduler_status=$?
            echo "Scheduler exited with status ${scheduler_status}; restarting in 5 seconds."
            sleep 5
        done
    ) &
fi

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
