#!/bin/sh
# 构建 amd64 基础镜像并推送阿里云。
set -e

IMAGE=${APP_IMAGE:-registry.cn-hangzhou.aliyuncs.com/shellphy/homepolit}
TAG=${1:-base}

docker buildx inspect homepilot >/dev/null 2>&1 \
    || docker buildx create --name homepilot --driver docker-container

docker buildx build \
    --builder homepilot \
    --file docker/Dockerfile \
    --platform linux/amd64 \
    --target base \
    --tag "$IMAGE:$TAG" \
    --provenance=false \
    --push \
    .

echo "已推送基础镜像 $IMAGE:$TAG"
echo "服务器仅在基础镜像变化时执行：docker compose pull && docker compose up -d"
