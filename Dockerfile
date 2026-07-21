# syntax=docker/dockerfile:1

# 只包含运行环境，业务代码由服务器 Git 工作区挂载。
ARG FRANKENPHP_IMAGE=dunglas/frankenphp:1.12.4-php8.5
ARG NODE_IMAGE=node:24.18.0-bookworm-slim

FROM ${NODE_IMAGE} AS node
FROM ${FRANKENPHP_IMAGE} AS base

RUN install-php-extensions pdo_sqlite sqlite3 bcmath intl zip pcntl opcache

COPY --from=composer:2.10.2 /usr/bin/composer /usr/local/bin/composer
COPY --from=node /usr/local/bin/ /usr/local/bin/
COPY --from=node /usr/local/lib/node_modules/ /usr/local/lib/node_modules/

COPY docker/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/healthcheck.php /usr/local/bin/healthcheck.php
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp

# 工具缓存不写入源码目录。
ENV HOME=/tmp \
    XDG_DATA_HOME=/app/storage/framework/caddy/data \
    XDG_CONFIG_HOME=/app/storage/framework/caddy/config \
    COMPOSER_HOME=/tmp/composer \
    npm_config_cache=/tmp/npm

WORKDIR /app

ENTRYPOINT ["entrypoint.sh"]
CMD ["app"]
