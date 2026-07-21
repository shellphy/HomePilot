COMPOSE := docker compose
EXEC := $(COMPOSE) exec -T app

.PHONY: web app env queue

web:
	$(EXEC) npm ci
	$(EXEC) npm run build

app:
	$(EXEC) composer install --no-dev --optimize-autoloader --no-interaction
	$(EXEC) php artisan migrate --force
	$(EXEC) php artisan optimize
	$(COMPOSE) restart app scheduler
	@$(MAKE) queue

env:
	$(EXEC) php artisan config:cache
	$(COMPOSE) up -d --force-recreate app worker scheduler

queue:
	$(EXEC) php artisan queue:restart
