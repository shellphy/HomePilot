# syntax=docker/dockerfile:1

# ---- Composer 依赖 ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress --ignore-platform-reqs

# ---- 前端资源构建（app.css 会扫描 vendor 内的分页视图，所以需要 vendor）----
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# ---- FrankenPHP 应用镜像（Caddy 内嵌 PHP，一个进程替代 php-fpm + nginx，原生流式支持 SSE）----
FROM dunglas/frankenphp:php8.5 AS app
RUN install-php-extensions pdo_sqlite bcmath intl zip pcntl opcache

WORKDIR /var/www/html
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

# 非 root 运行：相关目录归 www-data；具名卷首次创建继承此属主，运行期无需再 chown
RUN mkdir -p storage/app/public storage/logs storage/database \
        storage/framework/cache/data storage/framework/sessions storage/framework/views \
        /data/caddy /config/caddy \
    && composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache /data /config

COPY docker/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# 让非 root 的 www-data 也能绑定 80/443
RUN setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp

USER www-data

ENTRYPOINT ["entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
