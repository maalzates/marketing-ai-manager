# Marketing AI Manager — everything the environment needs, nothing else.
# Anything not here is reachable with `make exec` or `make artisan CMD="..."`.

DC    := docker compose
APP   := $(DC) exec app
APP_T := $(DC) exec -T app
NODE  := $(DC) run --rm --no-deps node

export UID := $(shell id -u)
export GID := $(shell id -g)

.DEFAULT_GOAL := help
.PHONY: help up start stop down exec logs test pint artisan

help: ## Show this help
	@grep -hE '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}'

# Safe to re-run: the build is cached, composer is a no-op when the lock is
# unchanged, and migrations are idempotent. Use `start` for a plain resume.
up: ## Build, install, migrate, seed and start everything
	@test -f src/.env || cp src/.env.example src/.env
	$(DC) build
	$(DC) up -d db redis
	@echo "waiting for MySQL..."
	@until $(DC) exec -T db mysqladmin ping -h localhost --silent >/dev/null 2>&1; do sleep 2; done
	@$(DC) exec -T db sh -c 'MYSQL_PWD="$$MYSQL_ROOT_PASSWORD" mysql -uroot' \
		< docker/mysql/01-create-testing-database.sql
	$(DC) run --rm --no-deps app composer install
	$(DC) up -d
	@grep -q '^APP_KEY=base64:' src/.env || $(APP_T) php artisan key:generate
	$(APP_T) php artisan migrate --force
	$(APP_T) php artisan db:seed --force
	@$(APP_T) sh -c 'test -L public/storage || php artisan storage:link'
	@echo ""
	@echo "  app     http://localhost$${HTTP_PORT:+:$$HTTP_PORT}"
	@echo "  mailpit http://localhost:$${MAILPIT_PORT:-8025}"
	@echo ""
	@echo "  Vite runs in the node container and is proxied by nginx; there is no"
	@echo "  separate port to open, and hot reload needs nothing running on the host."

start: ## Start the containers again after `stop`
	$(DC) start

stop: ## Stop the containers, keeping them around
	$(DC) stop

down: ## Stop and remove the containers (the database volume survives)
	$(DC) down

exec: ## Open a bash shell inside the php-fpm container
	$(APP) bash

logs: ## Tail every container's logs
	$(DC) logs -f --tail=100

test: ## Run the feature test suite against the MySQL testing schema
	@$(DC) exec -T db sh -c 'MYSQL_PWD="$$MYSQL_ROOT_PASSWORD" mysql -uroot' \
		< docker/mysql/01-create-testing-database.sql
	$(APP_T) php artisan test $(if $(FILTER),--filter=$(FILTER),)

pint: ## Fix PHP code style in place
	$(APP_T) ./vendor/bin/pint

# Escape hatch for everything the targets above do not cover:
#   make artisan CMD="migrate:fresh --seed"
#   make artisan CMD="tinker"
artisan: ## Run an artisan command: make artisan CMD="route:list"
	$(APP) php artisan $(CMD)
