#!/bin/sh
# 交叉构建 amd64 镜像并推到镜像仓库（开发机是 arm64、服务器是 x86-64，故需指定 platform）。
#
# 用法：docker/build-push.sh [tag]        # tag 缺省用当前 commit 短 hash
# 前置：docker login registry.cn-hangzhou.aliyuncs.com
set -e

IMAGE=${APP_IMAGE:-registry.cn-hangzhou.aliyuncs.com/shellphy/homepolit}
TAG=${1:-$(git rev-parse --short HEAD)}

# 默认的 docker 驱动不支持直接推多架构镜像，用 docker-container 驱动的构建器
docker buildx inspect homepilot >/dev/null 2>&1 \
    || docker buildx create --name homepilot --driver docker-container

# 同时打 :latest，服务器上 docker compose pull 默认取它；:$TAG 留作回滚锚点
docker buildx build \
    --builder homepilot \
    --platform linux/amd64 \
    --target app \
    --tag "$IMAGE:$TAG" \
    --tag "$IMAGE:latest" \
    --cache-from "type=registry,ref=$IMAGE:buildcache" \
    --cache-to "type=registry,ref=$IMAGE:buildcache,mode=max" \
    --push \
    .

echo "已推送 $IMAGE:$TAG"
echo "服务器上执行：docker compose pull && docker compose up -d"
