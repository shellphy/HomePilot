#!/bin/sh
set -e

# 仅在启动 php-fpm 服务时做初始化；docker compose run 执行的一次性命令直接放行
if [ "$1" != "php-fpm" ]; then
    exec "$@"
fi

if [ -z "$APP_KEY" ]; then
    echo "错误：APP_KEY 未设置。" >&2
    echo "请先执行 docker compose run --rm --no-deps app php artisan key:generate --show，" >&2
    echo "并将生成的 key 填入 .env 的 APP_KEY。" >&2
    exit 1
fi

tries=0
until php artisan migrate --force; do
    tries=$((tries + 1))
    if [ "$tries" -ge 10 ]; then
        echo "错误：等待数据库超时，迁移失败。" >&2
        exit 1
    fi
    echo "数据库尚未就绪，3 秒后重试（$tries/10）……"
    sleep 3
done

php artisan optimize

exec "$@"
