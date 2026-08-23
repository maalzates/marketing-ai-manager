# Marketing AI Manager — developer entrypoint.
# `make` with no target prints this list.

DC       := docker compose
DC_PROD  := docker compose --env-file .env.docker -f docker-compose.prod.yml
APP      := $(DC) exec app
APP_T    := $(DC) exec -T app
NODE     := $(DC) run --rm --no-deps node

export UID := $(shell id -u)
export GID := $(shell id -g)

.DEFAULT_GOAL := help
.PHONY: help init build up down stop start restart ps logs logs-app shell tinker \
        test test-filter coverage pint pint-fix migrate migrate-fresh seed \
        artisan composer npm npm-build npm-install fresh-install obs-up obs-down \
        clean prune deploy prod-up prod-down prod-logs prod-shell prod-migrate

help: ## Show this help
	@grep -hE '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

## ---------------------------------------------------------------- lifecycle

# Dependencies are installed with `run` before `up`, because the queue worker
# crash-loops on a fresh checkout that has no vendor/ yet. Re-running init is
# safe: the .env and the APP_KEY are only created when they are missing.
init: ## First-time setup: build images, install deps, migrate, boot everything
	@test -f src/.env || cp src/.env.example src/.env
	$(DC) build
	$(DC) up -d db redis
	@echo "waiting for MySQL to accept connections..."
	@until $(DC) exec -T db mysqladmin ping -h localhost --silent >/dev/null 2>&1; do sleep 2; done
	$(DC) run --rm --no-deps app composer install
	$(DC) up -d
	@grep -q '^APP_KEY=base64:' src/.env || $(APP_T) php artisan key:generate
	$(APP_T) php artisan migrate --force
	@$(APP_T) sh -c 'test -L public/storage || php artisan storage:link'
	@echo ""
	@echo "  app     http://localhost:$${HTTP_PORT:-8080}"
	@echo "  vite    http://localhost:$${VITE_PORT:-5173}"
	@echo "  mailpit http://localhost:$${MAILPIT_PORT:-8025}"

build: ## Rebuild the application images
	$(DC) build

up: ## Start all containers in the background
	$(DC) up -d

down: ## Stop and remove containers (volumes survive)
	$(DC) down

stop: ## Stop containers without removing them
	$(DC) stop

start: ## Start previously stopped containers
	$(DC) start

restart: ## Restart all containers
	$(DC) restart

ps: ## Show container status
	$(DC) ps

logs: ## Tail logs from every container
	$(DC) logs -f --tail=100

logs-app: ## Tail the Laravel log file
	$(APP) tail -f storage/logs/laravel.log

## ---------------------------------------------------------------- shells

shell: ## Open a bash shell in the app container
	$(APP) bash

tinker: ## Open Laravel Tinker
	$(APP) php artisan tinker

## ---------------------------------------------------------------- quality

test: ## Run the full PHPUnit suite
	$(APP_T) php artisan test

test-filter: ## Run a subset of tests: make test-filter FILTER=HealthEndpoint
	$(APP_T) php artisan test --filter=$(FILTER)

coverage: ## Run the suite with coverage (needs Xdebug in the image)
	$(APP_T) php artisan test --coverage

pint: ## Check code style (read-only)
	$(APP_T) ./vendor/bin/pint --test

pint-fix: ## Fix code style in place
	$(APP_T) ./vendor/bin/pint

## ---------------------------------------------------------------- database

migrate: ## Run pending migrations
	$(APP_T) php artisan migrate

migrate-fresh: ## Drop everything and re-migrate with seeds (DESTROYS dev data)
	$(APP_T) php artisan migrate:fresh --seed

seed: ## Run database seeders
	$(APP_T) php artisan db:seed

## ---------------------------------------------------------------- passthrough

artisan: ## Run any artisan command: make artisan CMD="route:list"
	$(APP) php artisan $(CMD)

composer: ## Run any composer command: make composer CMD="require foo/bar"
	$(APP) composer $(CMD)

npm: ## Run any npm command: make npm CMD="run build"
	$(NODE) npm $(CMD)

npm-install: ## Install frontend dependencies
	$(NODE) npm install

npm-build: ## Build production frontend assets
	$(NODE) npm run build

## ---------------------------------------------------------------- observability

obs-up: ## Start Loki + Promtail + Grafana (http://localhost:3000, admin/admin)
	$(DC) --profile observability up -d

obs-down: ## Stop the observability stack
	$(DC) --profile observability stop loki promtail grafana

## ---------------------------------------------------------------- cleanup

clean: ## Remove containers AND volumes (DESTROYS the dev database)
	$(DC) down -v

prune: ## Remove containers, volumes and locally built images
	$(DC) down -v --rmi local

## ---------------------------------------------------------------- production
## These target the server; run them from /var/www/marketing-ai-manager.

deploy: ## Run the production deployment script
	./scripts/deploy.sh

prod-up: ## Start the production stack
	$(DC_PROD) up -d

prod-down: ## Stop the production stack
	$(DC_PROD) down

prod-logs: ## Tail production logs
	$(DC_PROD) logs -f --tail=100

prod-shell: ## Shell into the production app container
	$(DC_PROD) exec app bash

prod-migrate: ## Run migrations in production
	$(DC_PROD) exec -T app php artisan migrate --force
