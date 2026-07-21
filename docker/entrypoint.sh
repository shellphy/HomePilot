#!/bin/sh
set -e

role="${1:-app}"
cd /app

case "$role" in
    app)
        exec frankenphp run --config /etc/frankenphp/Caddyfile
        ;;

    queue)
        shift
        exec php artisan queue:work "$@"
        ;;

    scheduler)
        exec php artisan schedule:work
        ;;

    *)
        exec php artisan "$@"
        ;;
esac
