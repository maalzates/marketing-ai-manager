#!/usr/bin/env bash
#
# Marketing AI Manager — production deploy.
#
# Idempotent: safe to re-run. Invoked by .github/workflows/deploy.yml over SSH, and
# run by hand from this directory on the server when a deploy has to be forced.
#
# Assumes setup-production.sh has already run on this box.

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/marketing-ai-manager}"
BRANCH="${DEPLOY_BRANCH:-main}"
COMPOSE="docker compose --env-file .env.docker -f docker-compose.prod.yml"

cd "$APP_DIR"

# APP_DOMAIN is needed for the health check's Host header (nginx serves by name).
# shellcheck source=/dev/null
set -a; source .env.docker; set +a

echo "==> Deploying $BRANCH into $APP_DIR"

# 1. Sync code. reset --hard (not merge) so a force-push on main still deploys.
echo "==> Fetching code"
git fetch origin "$BRANCH"
git reset --hard "origin/$BRANCH"

# 2. Bring the stack up. --build is cheap when the Dockerfile is unchanged.
echo "==> Building and starting containers"
$COMPOSE up -d --build

echo "==> Waiting for MySQL"
until $COMPOSE exec -T db mysqladmin ping -h localhost --silent >/dev/null 2>&1; do
    sleep 2
done

# 3. Backend dependencies.
echo "==> Installing PHP dependencies"
$COMPOSE exec -T app composer install --no-dev --optimize-autoloader --no-interaction

# 4. Frontend assets. Built in a throwaway Node container so the server never
#    needs a Node toolchain of its own.
echo "==> Building frontend assets"
docker run --rm \
    -v "$APP_DIR/src:/app" \
    -w /app \
    node:22-alpine \
    sh -c "npm ci --no-audit --no-fund && npm run build"

# 5. Writable paths. php-fpm runs as www-data, but a fresh clone and anything git writes
#    lands as root, so Laravel cannot write its log, its cache or a session and every
#    request becomes a 500 with nothing in the log to explain it.
echo "==> Fixing writable paths"
$COMPOSE exec -T app sh -c 'chown -R www-data:www-data storage bootstrap/cache && chmod -R u+rwX storage bootstrap/cache'

# 6. Schema.
echo "==> Running migrations"
$COMPOSE exec -T app php artisan migrate --force

# 7. Caches. Clear first — a stale config cache makes the rebuild read old values.
echo "==> Rebuilding caches"
$COMPOSE exec -T app php artisan optimize:clear
$COMPOSE exec -T app php artisan config:cache
$COMPOSE exec -T app php artisan route:cache
$COMPOSE exec -T app php artisan view:cache
$COMPOSE exec -T app php artisan event:cache

# 8. Long-running workers hold the old code in memory until restarted.
echo "==> Restarting workers"
$COMPOSE exec -T app php artisan queue:restart
$COMPOSE restart queue scheduler

# 9. Smoke test through nginx. Plain HTTP on purpose: TLS terminates at Cloudflare, so
#    this origin has no 443 listener and no certificate to check against.
echo "==> Health check"
for _ in $(seq 1 10); do
    if curl -fsS -o /dev/null -H "Host: ${APP_DOMAIN}" "http://127.0.0.1/up"; then
        echo "==> Deploy OK ($(git rev-parse --short HEAD))"
        exit 0
    fi
    sleep 3
done

echo "!! Health check failed after deploy — inspect: $COMPOSE logs --tail=100 app nginx" >&2
exit 1
