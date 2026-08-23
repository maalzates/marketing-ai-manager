#!/usr/bin/env bash
#
# Marketing AI Manager — one-time production server setup.
#
# Target: a FRESH Ubuntu 22.04 / 24.04 VPS, run as root.
# Everything after this first run is handled by scripts/deploy.sh.
#
#   ssh root@YOUR_SERVER
#   curl -fsSL https://raw.githubusercontent.com/maalzates/marketing-ai-manager/main/scripts/setup-production.sh -o setup.sh
#   bash setup.sh
#
# Steps 8 onward are interactive: they stop and wait while you paste a GitHub
# deploy key and fill in the two .env files.

set -euo pipefail

REPO_SSH="${REPO_SSH:-git@github.com:maalzates/marketing-ai-manager.git}"
APP_DIR="${APP_DIR:-/var/www/marketing-ai-manager}"
SWAP_SIZE="${SWAP_SIZE:-4G}"
COMPOSE="docker compose --env-file .env.docker -f docker-compose.prod.yml"

if [ "$EUID" -ne 0 ]; then
    echo "Run as root (sudo su)." >&2
    exit 1
fi

step() { echo -e "\n\033[1;34m==> $1\033[0m"; }

step "1/13 Updating the system"
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get upgrade -y

step "2/13 Installing base packages"
apt-get install -y --no-install-recommends \
    ca-certificates curl git gnupg lsb-release unzip vim htop \
    ufw fail2ban certbot cron

step "3/13 Installing Docker Engine"
if ! command -v docker >/dev/null 2>&1; then
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
        | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    chmod a+r /etc/apt/keyrings/docker.gpg
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" \
        > /etc/apt/sources.list.d/docker.list
    apt-get update
    apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
    systemctl enable --now docker
fi
docker --version

step "4/13 Configuring the firewall"
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

step "5/13 Enabling fail2ban"
systemctl enable --now fail2ban

step "6/13 Configuring ${SWAP_SIZE} of swap"
if [ ! -f /swapfile ]; then
    fallocate -l "$SWAP_SIZE" /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi
free -h | head -3

step "7/13 Enabling unattended security updates"
apt-get install -y unattended-upgrades
dpkg-reconfigure -f noninteractive unattended-upgrades

step "8/13 GitHub deploy key"
if [ ! -f /root/.ssh/id_ed25519 ]; then
    ssh-keygen -t ed25519 -N "" -f /root/.ssh/id_ed25519 -C "marketing-ai-manager@$(hostname)"
fi
ssh-keyscan -t ed25519 github.com >> /root/.ssh/known_hosts 2>/dev/null
echo
echo "Add this key as a read-only deploy key at:"
echo "  https://github.com/maalzates/marketing-ai-manager/settings/keys"
echo
cat /root/.ssh/id_ed25519.pub
echo
read -r -p "Press Enter once the deploy key is added..."
ssh -T git@github.com 2>&1 | head -1 || true

step "9/13 Cloning the repository"
mkdir -p "$(dirname "$APP_DIR")"
if [ ! -d "$APP_DIR/.git" ]; then
    git clone "$REPO_SSH" "$APP_DIR"
fi
cd "$APP_DIR"

step "10/13 Creating environment files"
if [ ! -f .env.docker ]; then
    cp .env.docker.example .env.docker
    # Pre-fill the secrets so nothing ships with a placeholder password.
    sed -i "s|^MYSQL_PASSWORD=.*|MYSQL_PASSWORD=$(openssl rand -base64 24 | tr -d '/+=')|" .env.docker
    sed -i "s|^MYSQL_ROOT_PASSWORD=.*|MYSQL_ROOT_PASSWORD=$(openssl rand -base64 24 | tr -d '/+=')|" .env.docker
    sed -i "s|^GRAFANA_ADMIN_PASSWORD=.*|GRAFANA_ADMIN_PASSWORD=$(openssl rand -base64 24 | tr -d '/+=')|" .env.docker
    echo "Created .env.docker with generated passwords."
fi
echo "Set APP_DOMAIN in .env.docker now (it selects the TLS certificate)."
read -r -p "Press Enter when .env.docker is correct (edit with: nano $APP_DIR/.env.docker)..."

set -a; source .env.docker; set +a
: "${APP_DOMAIN:?APP_DOMAIN must be set in .env.docker}"

if [ ! -f src/.env ]; then
    cp src/.env.example src/.env
    sed -i "s|^APP_ENV=.*|APP_ENV=production|" src/.env
    sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|" src/.env
    sed -i "s|^APP_URL=.*|APP_URL=https://${APP_DOMAIN}|" src/.env
    sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${MYSQL_DATABASE}|" src/.env
    sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${MYSQL_USER}|" src/.env
    sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${MYSQL_PASSWORD}|" src/.env
    sed -i "s|^LOG_LEVEL=.*|LOG_LEVEL=warning|" src/.env
    echo "Created src/.env from the example."
fi
echo "Fill in the remaining Laravel secrets (ANTHROPIC_API_KEY, mail, ...)."
read -r -p "Press Enter when src/.env is correct (edit with: nano $APP_DIR/src/.env)..."

step "11/13 Issuing the TLS certificate for ${APP_DOMAIN}"
echo "DNS for ${APP_DOMAIN} must already point at this server's IP."
read -r -p "Press Enter to continue (Ctrl-C to abort and fix DNS first)..."
if [ ! -d "/etc/letsencrypt/live/${APP_DOMAIN}" ]; then
    # Standalone: nginx is not running yet, so certbot can own port 80.
    certbot certonly --standalone --non-interactive --agree-tos \
        -d "${APP_DOMAIN}" -m "admin@${APP_DOMAIN}"
fi
# Renewals happen while nginx holds port 80, so switch to the webroot challenge
# and reload nginx afterwards.
cat > /etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh <<'HOOK'
#!/bin/sh
docker exec marketing-ai-nginx nginx -s reload || true
HOOK
chmod +x /etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh
systemctl enable --now certbot.timer 2>/dev/null || true

step "12/13 Building and starting the stack"
$COMPOSE build
$COMPOSE up -d
until $COMPOSE exec -T db mysqladmin ping -h localhost --silent >/dev/null 2>&1; do sleep 2; done

$COMPOSE exec -T app composer install --no-dev --optimize-autoloader --no-interaction
grep -q '^APP_KEY=base64:' src/.env || $COMPOSE exec -T app php artisan key:generate --force
$COMPOSE exec -T app php artisan migrate --force
$COMPOSE exec -T app php artisan storage:link || true
docker run --rm -v "$APP_DIR/src:/app" -w /app node:22-alpine \
    sh -c "npm ci --no-audit --no-fund && npm run build"
$COMPOSE exec -T app php artisan config:cache
$COMPOSE exec -T app php artisan route:cache
$COMPOSE exec -T app php artisan view:cache

step "13/13 Scheduling nightly database backups"
mkdir -p /var/backups/marketing-ai
cat > /etc/cron.daily/marketing-ai-db-backup <<CRON
#!/bin/sh
cd ${APP_DIR} || exit 0
. ./.env.docker
docker compose --env-file .env.docker -f docker-compose.prod.yml exec -T db \
    mysqldump -u root -p"\${MYSQL_ROOT_PASSWORD}" --single-transaction "\${MYSQL_DATABASE}" \
    | gzip > /var/backups/marketing-ai/db-\$(date +%F).sql.gz
find /var/backups/marketing-ai -name 'db-*.sql.gz' -mtime +14 -delete
CRON
chmod +x /etc/cron.daily/marketing-ai-db-backup

cat <<DONE

============================================================
 Setup complete.

   App          https://${APP_DOMAIN}
   Code         ${APP_DIR}
   Backups      /var/backups/marketing-ai (nightly, 14 days)

 Next:
   1. Add the GitHub Actions secrets (SSH_HOST, SSH_USERNAME,
      SSH_PRIVATE_KEY, SSH_PORT) — see guidelines/DEPLOYMENT-GUIDE.md.
   2. Push to main and watch the deploy workflow run.

 Back these up somewhere safe, they are not in git:
   ${APP_DIR}/.env.docker
   ${APP_DIR}/src/.env
============================================================
DONE
