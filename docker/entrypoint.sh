#!/bin/sh
set -e

# 仅在启动 FrankenPHP 服务时做初始化；docker compose run 执行的一次性命令直接放行
if [ "$1" != "frankenphp" ]; then
    exec "$@"
fi

if [ -z "$APP_KEY" ]; then
    echo "错误：APP_KEY 未设置。" >&2
    echo "请先执行 docker compose run --rm --no-deps app php artisan key:generate --show，" >&2
    echo "并将生成的 key 填入 .env 的 APP_KEY。" >&2
    exit 1
fi

# SQLite 库文件在具名卷里，首次启动创建（卷目录已是 www-data 属主）
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ] && [ -n "$DB_DATABASE" ]; then
    mkdir -p "$(dirname "$DB_DATABASE")"
    [ -f "$DB_DATABASE" ] || touch "$DB_DATABASE"
fi

php artisan migrate --force

php artisan optimize

exec "$@"
