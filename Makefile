COMPOSE := docker compose
EXEC := $(COMPOSE) exec -T app

.PHONY: web app env frankenphp queue

web:
	$(EXEC) npm ci
	$(EXEC) npm run build

app:
	$(EXEC) composer install --no-dev --optimize-autoloader --no-interaction
	$(EXEC) php artisan migrate --force
	$(EXEC) php artisan optimize
	@$(MAKE) frankenphp
	@$(MAKE) queue

frankenphp:
	$(EXEC) frankenphp reload --config /etc/frankenphp/Caddyfile

queue:
	$(EXEC) php artisan queue:restart
