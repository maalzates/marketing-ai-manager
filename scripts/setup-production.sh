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
    ufw fail2ban cron jq dnsutils

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
# Only SSH is opened here. Port 80 is opened per Cloudflare range by
# cloudflare-ranges-refresh in step 11 — the origin serves plaintext, so it must be
# unreachable except through Cloudflare. Port 443 is never opened: nothing listens there.
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp
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
echo "Set APP_DOMAIN in .env.docker now (nginx serves by name; TLS is Cloudflare's)."
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
echo "Fill in the remaining Laravel secrets (ANTHROPIC_API_KEY, Google, Meta, ...)."
read -r -p "Press Enter when src/.env is correct (edit with: nano $APP_DIR/src/.env)..."

step "11/13 Cloudflare ranges and the fail2ban exemption"
# No certificate is issued here. TLS terminates at Cloudflare in Flexible mode, so a
# Let's Encrypt certificate would be dead weight — Cloudflare never presents it. What the
# origin does need is two things this step installs.
echo "In Cloudflare: both A records proxied (orange cloud), SSL/TLS mode = Flexible."
read -r -p "Press Enter once that is set (Ctrl-C to abort)..."

mkdir -p /etc/nginx/cloudflare

# 11.1 Cloudflare: nginx real_ip config plus the UFW allowlist for :80, from one source.
cat > /usr/local/sbin/cloudflare-ranges-refresh <<'SCRIPT'
#!/usr/bin/env bash
# Two jobs, one source of truth:
#   1. nginx real_ip config, so logs and rate limits see the visitor and not Cloudflare.
#   2. a UFW allowlist for :80, because with Flexible TLS the origin serves plaintext and
#      must be unreachable except through Cloudflare.
set -euo pipefail
CONF=/etc/nginx/cloudflare/real-ip.conf
TMP=$(mktemp); TMP4=$(mktemp); TMP6=$(mktemp)
trap "rm -f $TMP $TMP4 $TMP6" EXIT

curl -fsSL --max-time 20 https://www.cloudflare.com/ips-v4 -o "$TMP4"
curl -fsSL --max-time 20 https://www.cloudflare.com/ips-v6 -o "$TMP6"
[ "$(wc -l < "$TMP4")" -ge 5 ] || { echo "ipv4 list too short, refusing" >&2; exit 1; }

{
  echo "# Generated by cloudflare-ranges-refresh. Do not edit."
  while read -r cidr; do [ -n "$cidr" ] && echo "set_real_ip_from $cidr;"; done < "$TMP4"
  while read -r cidr; do [ -n "$cidr" ] && echo "set_real_ip_from $cidr;"; done < "$TMP6"
  echo "real_ip_header CF-Connecting-IP;"
  echo "real_ip_recursive on;"
} > "$TMP"
mv "$TMP" "$CONF"; chmod 644 "$CONF"

# Rebuild the :80 allowlist from scratch so a removed Cloudflare range stops being allowed.
ufw --force delete allow 80/tcp >/dev/null 2>&1 || true
while read -r cidr; do [ -n "$cidr" ] && ufw allow from "$cidr" to any port 80 proto tcp >/dev/null; done < "$TMP4"
while read -r cidr; do [ -n "$cidr" ] && ufw allow from "$cidr" to any port 80 proto tcp >/dev/null; done < "$TMP6"

echo "$(wc -l < "$TMP4") v4 + $(wc -l < "$TMP6") v6 Cloudflare ranges applied"
SCRIPT
chmod +x /usr/local/sbin/cloudflare-ranges-refresh

cat > /etc/systemd/system/cloudflare-ranges-refresh.service <<'UNIT'
[Unit]
Description=Refresh Cloudflare edge ranges for nginx real_ip and the UFW allowlist on :80
After=network-online.target
Wants=network-online.target

[Service]
Type=oneshot
ExecStart=/usr/local/sbin/cloudflare-ranges-refresh
ExecStartPost=-/usr/bin/docker exec marketing-ai-nginx nginx -s reload
UNIT

cat > /etc/systemd/system/cloudflare-ranges-refresh.timer <<'UNIT'
[Unit]
Description=Weekly refresh of the Cloudflare edge ranges

[Timer]
OnCalendar=weekly
RandomizedDelaySec=2h
Persistent=true

[Install]
WantedBy=timers.target
UNIT

# 11.2 fail2ban: exempt the GitHub Actions runners that deploy over SSH. Their ranges
# change without notice, and once one is banned every deploy fails with nothing in the
# Actions log but a timeout.
cat > /usr/local/sbin/github-ranges-refresh <<'SCRIPT'
#!/usr/bin/env bash
# Caches the CIDR ranges GitHub Actions runners use. They change without notice, and a
# banned range makes every deploy fail with no obvious cause, so this refreshes daily.
set -euo pipefail
DEST=/etc/fail2ban/github-actions-ranges.txt
TMP=$(mktemp)
trap "rm -f $TMP" EXIT
curl -fsSL --max-time 30 https://api.github.com/meta \
  | jq -r ".actions[]?" > "$TMP"
# Never replace a good list with an empty one: a bad fetch would un-exempt GitHub.
if [ "$(wc -l < "$TMP")" -lt 100 ]; then
  echo "refusing to install a suspiciously short list ($(wc -l < "$TMP") entries)" >&2
  exit 1
fi
mv "$TMP" "$DEST"
chmod 644 "$DEST"
echo "$(wc -l < "$DEST") ranges cached"
SCRIPT
chmod +x /usr/local/sbin/github-ranges-refresh

cat > /usr/local/sbin/fail2ban-ignore-github <<'SCRIPT'
#!/usr/bin/env python3
"""fail2ban ignorecommand: exit 0 when the IP belongs to GitHub Actions.

Checked against a cached list rather than a live call: fail2ban runs this on every
candidate, and a network round trip per attempt would be its own denial of service.
"""
import ipaddress, sys, pathlib

LIST = pathlib.Path("/etc/fail2ban/github-actions-ranges.txt")

def main() -> int:
    if len(sys.argv) < 2 or not LIST.exists():
        return 1
    try:
        ip = ipaddress.ip_address(sys.argv[1])
    except ValueError:
        return 1
    for line in LIST.read_text().splitlines():
        line = line.strip()
        if not line:
            continue
        try:
            if ip in ipaddress.ip_network(line, strict=False):
                return 0
        except ValueError:
            continue
    return 1

sys.exit(main())
SCRIPT
chmod +x /usr/local/sbin/fail2ban-ignore-github

cat > /etc/fail2ban/jail.local <<'JAIL'
[DEFAULT]
backend  = systemd
bantime  = 1h
findtime = 10m
maxretry = 5

# GitHub Actions runners deploy over SSH from ranges that change without notice. Once a
# range is banned every deploy fails, and the cause is invisible from the CI logs — so the
# runners are exempted by lookup against a daily-refreshed cache instead of a static list
# (there are ~7000 ranges; ignoreip cannot carry that).
ignorecommand = /usr/local/sbin/fail2ban-ignore-github <ip>

[sshd]
enabled  = true
# Deliberately NOT aggressive: that mode bans on "Connection closed by authenticating
# user", which is exactly what a CI runner reconnecting looks like.
mode     = normal
maxretry = 5
JAIL

cat > /etc/systemd/system/github-ranges-refresh.service <<'UNIT'
[Unit]
Description=Refresh the cached GitHub Actions IP ranges used by the fail2ban exemption
After=network-online.target
Wants=network-online.target

[Service]
Type=oneshot
ExecStart=/usr/local/sbin/github-ranges-refresh
UNIT

cat > /etc/systemd/system/github-ranges-refresh.timer <<'UNIT'
[Unit]
Description=Daily refresh of the GitHub Actions IP ranges

[Timer]
OnCalendar=daily
RandomizedDelaySec=1h
Persistent=true

[Install]
WantedBy=timers.target
UNIT

# Run both once now. The nginx template includes /etc/nginx/cloudflare/real-ip.conf, so
# nginx refuses to start until the first run has written it — this has to happen before
# the stack comes up in step 12.
/usr/local/sbin/cloudflare-ranges-refresh
/usr/local/sbin/github-ranges-refresh
systemctl restart fail2ban
systemctl daemon-reload
systemctl enable --now cloudflare-ranges-refresh.timer github-ranges-refresh.timer

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
   1. Confirm in Cloudflare: both A records proxied, SSL/TLS mode Flexible.
      Full or Full (strict) will 521 — this origin has no :443 listener.
   2. Add the GitHub Actions secrets (SSH_HOST, SSH_USERNAME,
      SSH_PRIVATE_KEY, SSH_PORT) — see docs/deployment.md.
   3. Push to main and watch the deploy workflow run.

 Back these up somewhere safe, they are not in git:
   ${APP_DIR}/.env.docker
   ${APP_DIR}/src/.env
============================================================
DONE
