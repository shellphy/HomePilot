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

# ---- PHP-FPM 应用镜像 ----
FROM php:8.5-fpm-alpine AS app
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql bcmath intl zip pcntl opcache

WORKDIR /var/www/html
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN mkdir -p storage/app/public storage/logs \
        storage/framework/cache/data storage/framework/sessions storage/framework/views \
    && composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# ---- Nginx 镜像（自带 public 静态文件，与 app 同路径）----
FROM nginx:alpine AS web
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
