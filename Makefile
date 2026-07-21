COMPOSE := docker compose
RUN := $(COMPOSE) run --rm --no-deps cli

.PHONY: init up pull web app env queue backup status logs image test

init:
	$(COMPOSE) run --rm --no-deps volume-init

up: init
	$(COMPOSE) up -d

# 基础镜像更新时使用。
pull:
	$(COMPOSE) pull
	$(COMPOSE) up -d

web: init
	$(RUN) npm ci
	$(RUN) npm run build

app: init
	$(RUN) composer install --no-dev --optimize-autoloader --no-interaction
	$(RUN) php artisan migrate --force
	$(RUN) php artisan optimize
	$(COMPOSE) up -d --no-deps app worker scheduler
	$(COMPOSE) restart app scheduler
	@$(MAKE) queue

env: init
	$(RUN) php artisan optimize
	$(COMPOSE) restart app scheduler
	@$(MAKE) queue

queue:
	$(RUN) php artisan queue:restart

backup:
	$(RUN) php artisan app:backup

status:
	$(COMPOSE) ps

logs:
	$(COMPOSE) logs --tail=200 -f app worker scheduler

# 在开发机构建 amd64 基础镜像并推送阿里云。
image:
	./docker/build-push.sh

test:
	php artisan test --compact
