#!/bin/sh
# 在可访问海外仓库的开发机交叉构建 amd64 运行时基础镜像并推送阿里云。
# 业务代码不进入镜像；普通发布只需服务器 git pull 后执行 make 目标。
set -e

IMAGE=${APP_IMAGE:-registry.cn-hangzhou.aliyuncs.com/shellphy/homepolit}
TAG=${1:-base}

docker buildx inspect homepilot >/dev/null 2>&1 \
    || docker buildx create --name homepilot --driver docker-container

docker buildx build \
    --builder homepilot \
    --platform linux/amd64 \
    --target base \
    --tag "$IMAGE:$TAG" \
    --provenance=false \
    --push \
    .

echo "已推送基础镜像 $IMAGE:$TAG"
echo "服务器仅在基础镜像变化时执行：make pull"
