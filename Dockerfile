# syntax=docker/dockerfile:1

# 生产运行时基础镜像：业务代码由服务器 Git 工作区 bind mount 到 /app。
# 只有 PHP 扩展、PHP/Node/Composer 版本变化时才需要重新构建并推送阿里云。
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

# 容器使用宿主机 uid/gid 运行，所有工具缓存放到可写的临时目录。
ENV HOME=/tmp \
    XDG_DATA_HOME=/tmp \
    XDG_CONFIG_HOME=/tmp \
    COMPOSER_HOME=/tmp/composer \
    npm_config_cache=/tmp/npm

WORKDIR /app

ENTRYPOINT ["entrypoint.sh"]
CMD ["app"]
