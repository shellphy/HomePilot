COMPOSE := docker compose
RUN := $(COMPOSE) run --rm --no-deps cli

.PHONY: init up pull web app env queue backup status logs image test

# 初始化具名卷和宿主机备份目录权限；各部署目标会自动执行。
init:
	$(COMPOSE) run --rm --no-deps volume-init

up: init
	$(COMPOSE) up -d

# 仅当基础镜像有更新时使用；普通 git pull 不需要 pull 镜像。
pull:
	$(COMPOSE) pull
	$(COMPOSE) up -d

# 前端依赖或资源变化：git pull && make web
web: init
	$(RUN) npm ci
	$(RUN) npm run build

# PHP 代码、依赖或迁移变化：git pull && make app
app: init
	$(RUN) composer install --no-dev --optimize-autoloader --no-interaction
	$(RUN) php artisan migrate --force
	$(RUN) php artisan optimize
	$(COMPOSE) up -d --no-deps app worker scheduler
	$(COMPOSE) restart app scheduler
	@$(MAKE) queue

# 只修改 .env 时使用。
env: init
	$(RUN) php artisan optimize
	$(COMPOSE) restart app scheduler
	@$(MAKE) queue

# 队列代码变化或单独需要重启 worker 时使用。
queue:
	$(RUN) php artisan queue:restart

backup:
	$(RUN) php artisan app:backup

status:
	$(COMPOSE) ps

logs:
	$(COMPOSE) logs --tail=200 -f app worker scheduler

# 在可访问海外镜像的开发机上构建基础镜像并推送阿里云。
image:
	./docker/build-push.sh

test:
	php artisan test --compact
