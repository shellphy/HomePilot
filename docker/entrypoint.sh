#!/bin/sh
set -e

role="${1:-app}"
cd /app

case "$role" in
    app)
        if [ -z "$APP_KEY" ]; then
            echo "错误：APP_KEY 未设置。" >&2
            exit 1
        fi

        if [ "${SERVER_NAME:-:80}" != ":80" ]; then
            if [ "${APP_ENV:-}" != "production" ]; then
                echo "错误：HTTPS 部署要求 APP_ENV=production（必须全小写）。" >&2
                exit 1
            fi

            if [ "${APP_DEBUG:-false}" != "false" ]; then
                echo "错误：生产部署要求 APP_DEBUG=false。" >&2
                exit 1
            fi

            case "${APP_URL:-}" in
                https://*) ;;
                *)
                    echo "错误：HTTPS 部署要求 APP_URL 使用 https://。" >&2
                    exit 1
                    ;;
            esac
        fi

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
        exec "$@"
        ;;
esac
