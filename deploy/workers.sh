#!/bin/sh
# Source this file after Laravel has been configured.
if [ "${RUN_SCHEDULER:-false}" = "true" ]; then
    (while true; do
        status=0
        php artisan schedule:work --no-interaction || status=$?
        echo "Scheduler exited ($status); restarting in 5 seconds."
        sleep 5
    done) &
fi
if [ "${RUN_QUEUE_WORKER:-false}" = "true" ]; then
    (while true; do
        status=0
        php artisan queue:work --sleep=3 --tries=3 --timeout=60 --no-interaction || status=$?
        echo "Queue worker exited ($status); restarting in 5 seconds."
        sleep 5
    done) &
fi
