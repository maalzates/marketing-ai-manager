# Deployment Guide — Marketing AI Manager

Everything needed to take this repo from "clean checkout" to "running on a VPS
with automatic deploys on every push to `main`".

Read this once end to end before touching a server. The steps are ordered
because they depend on each other — DNS before TLS, TLS before nginx, nginx
before the first deploy.

---

## Table of contents

1. [What you are building](#1-what-you-are-building)
2. [Prerequisites](#2-prerequisites)
3. [DNS first](#3-dns-first)
4. [Server setup (the scripted path)](#4-server-setup-the-scripted-path)
5. [Server setup (the manual path)](#5-server-setup-the-manual-path)
6. [The two environment files](#6-the-two-environment-files)
7. [TLS certificates](#7-tls-certificates)
8. [Automatic deployment with GitHub Actions](#8-automatic-deployment-with-github-actions)
9. [What `deploy.sh` actually does](#9-what-deploysh-actually-does)
10. [Rollback](#10-rollback)
11. [Logs and observability](#11-logs-and-observability)
12. [Backups](#12-backups)
13. [Troubleshooting](#13-troubleshooting)
14. [Security checklist](#14-security-checklist)

---

## 1. What you are building

```
                    GitHub (main)
                         │  push
                         ▼
                  Actions: CI ──► Actions: Deploy
                                       │ ssh
                                       ▼
┌──────────────────────── VPS (Ubuntu 24.04) ────────────────────────┐
│                                                                    │
│  nginx  :80 → 301 → :443 (TLS, Let's Encrypt)                      │
│    │                                                               │
│    ├── /build/*, /*.css, /*.js  →  static files from src/public    │
│    └── everything else          →  php-fpm (app)                   │
│                                        │                           │
│                          ┌─────────────┼─────────────┐             │
│                          ▼             ▼             ▼             │
│                     MySQL 8.4      Redis 7      queue + scheduler  │
│                  (127.0.0.1:3306)  (internal)   (php-fpm image)    │
│                                                                    │
│  optional profile: Loki ← Promtail  →  Grafana (127.0.0.1:3000)    │
└────────────────────────────────────────────────────────────────────┘
```

Everything runs from `/var/www/marketing-ai-manager`, which is a git checkout.
Deploying is: pull, rebuild assets, migrate, rebuild caches, restart workers.

**Why a git checkout and not an image registry?** One box, one app, no
orchestrator. A checkout keeps the deploy to a `git reset --hard` and removes
the registry from the critical path. If this ever grows to more than one server,
that is the moment to switch to pushing images.

---

## 2. Prerequisites

| Thing | Notes |
|---|---|
| A VPS | Ubuntu 22.04 or 24.04, 2 vCPU / 4 GB RAM minimum. Hostinger, Hetzner, DigitalOcean — any of them. |
| Root SSH access | You will harden it later. |
| A domain | Pointed at the VPS. Cloudflare or the registrar's own DNS, either works. |
| A GitHub repo | `maalzates/marketing-ai-manager`, with Actions enabled. |
| API keys | `ANTHROPIC_API_KEY`, plus whatever the app grows into. |

Nothing needs to be installed on the server by hand — `setup-production.sh`
brings Docker with it. Node is **not** required on the server: assets are built
in a throwaway `node:22-alpine` container.

---

## 3. DNS first

TLS issuance validates that you control the domain by resolving it. So DNS goes
before everything else — a certificate request against a domain that does not
resolve to this box fails, and Let's Encrypt rate-limits failures.

Create one record:

| Type | Name | Value | Proxy |
|---|---|---|---|
| A | `@` (or `marketing`) | your VPS IPv4 | **DNS only** for the first issuance |

Then verify from your laptop:

```bash
dig +short marketing.example.com
# must print the VPS IP, nothing else
```

**If you use Cloudflare:** keep the record grey-clouded (DNS only) until the
certificate is issued. Once it works, you may turn the orange cloud on — and if
you do, set SSL/TLS mode to **Full (strict)**. Any other mode either breaks the
redirect loop or silently strips TLS between Cloudflare and your origin.

DNS propagation is usually seconds, occasionally an hour. Wait for `dig` to be
right before continuing.

---

## 4. Server setup (the scripted path)

```bash
ssh root@YOUR_SERVER_IP

curl -fsSL https://raw.githubusercontent.com/maalzates/marketing-ai-manager/main/scripts/setup-production.sh -o setup.sh
less setup.sh          # read it before running it
bash setup.sh
```

The script is idempotent and pauses at every point where it needs you:

| Step | What happens | You do |
|---|---|---|
| 1–2 | System update, base packages | nothing |
| 3 | Docker Engine + Compose plugin | nothing |
| 4 | UFW: deny inbound except 22, 80, 443 | nothing |
| 5 | fail2ban enabled | nothing |
| 6 | 4 GB swap file | nothing |
| 7 | Unattended security upgrades | nothing |
| 8 | Generates an ed25519 key, prints the public half | **add it as a deploy key on GitHub**, then press Enter |
| 9 | Clones the repo to `/var/www/marketing-ai-manager` | nothing |
| 10 | Creates `.env.docker` (random passwords) and `src/.env` | **set `APP_DOMAIN`, then the Laravel secrets**, press Enter between the two |
| 11 | Issues the TLS certificate with certbot standalone | confirm DNS is ready |
| 12 | Builds images, starts the stack, migrates, builds assets, caches config | nothing |
| 13 | Installs a nightly `mysqldump` cron, 14-day retention | nothing |

At the end you should be able to open `https://your-domain` and see the SPA.

The deploy key from step 8 goes here:
`https://github.com/maalzates/marketing-ai-manager/settings/keys` → **Add deploy
key** → paste → leave "Allow write access" **unchecked**.

---

## 5. Server setup (the manual path)

Use this if the script fails halfway, or if you want to understand each piece.
It is exactly what the script does.

```bash
# 5.1 System
apt-get update && apt-get upgrade -y
apt-get install -y ca-certificates curl git gnupg lsb-release unzip ufw fail2ban certbot cron

# 5.2 Docker
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
chmod a+r /etc/apt/keyrings/docker.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" > /etc/apt/sources.list.d/docker.list
apt-get update
apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
systemctl enable --now docker

# 5.3 Firewall — deny everything inbound except SSH and the web
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp && ufw allow 80/tcp && ufw allow 443/tcp
ufw --force enable

# 5.4 Swap — 4 GB. MySQL + composer + a Vite build will OOM a 4 GB box without it.
fallocate -l 4G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab

# 5.5 Deploy key
ssh-keygen -t ed25519 -N "" -f /root/.ssh/id_ed25519 -C "marketing-ai-manager@$(hostname)"
cat /root/.ssh/id_ed25519.pub     # add on GitHub as a read-only deploy key
ssh -T git@github.com             # expect "successfully authenticated"

# 5.6 Clone
mkdir -p /var/www && git clone git@github.com:maalzates/marketing-ai-manager.git /var/www/marketing-ai-manager
cd /var/www/marketing-ai-manager
```

Then continue with sections 6 and 7, and finish with:

```bash
docker compose --env-file .env.docker -f docker-compose.prod.yml up -d --build
./scripts/deploy.sh
```

---

## 6. The two environment files

This trips people up, so read it slowly. There are **two** environment files and
they are read by **different programs**.

### `.env.docker` — read by Docker Compose

Sets the domain nginx serves and the credentials MySQL is *created with*.
Compose reads it via `--env-file .env.docker`.

```bash
cp .env.docker.example .env.docker
nano .env.docker
```

```dotenv
APP_DOMAIN=marketing.example.com
MYSQL_DATABASE=marketing_ai_production
MYSQL_USER=marketing_ai
MYSQL_PASSWORD=<openssl rand -base64 24>
MYSQL_ROOT_PASSWORD=<openssl rand -base64 24>
GRAFANA_ADMIN_USER=admin
GRAFANA_ADMIN_PASSWORD=<openssl rand -base64 24>
```

### `src/.env` — read by Laravel

Sets what the *application* does, including the credentials it *connects with*.

```bash
cp src/.env.example src/.env
nano src/.env
```

```dotenv
APP_NAME="Marketing AI Manager"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://marketing.example.com
APP_KEY=                     # generated below, never blank

DB_CONNECTION=mysql
DB_HOST=db                   # the compose service name, not localhost
DB_PORT=3306
DB_DATABASE=marketing_ai_production   # must match MYSQL_DATABASE
DB_USERNAME=marketing_ai              # must match MYSQL_USER
DB_PASSWORD=<the same MYSQL_PASSWORD>

REDIS_HOST=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

LOG_LEVEL=warning

ANTHROPIC_API_KEY=sk-ant-...
```

Generate the app key once the containers are up:

```bash
docker compose --env-file .env.docker -f docker-compose.prod.yml exec -T app php artisan key:generate --force
```

> **The database credentials appear in both files on purpose.** MySQL is
> *created* with the values in `.env.docker`; Laravel *connects* with the values
> in `src/.env`. If they drift, the app cannot log in to its own database — and
> because MySQL only reads its variables when the data volume is first created,
> changing `.env.docker` later does **not** change an existing user's password.
> To rotate it you `ALTER USER` inside MySQL and update `src/.env`.

Neither file is in git. Back both up somewhere you will still have access to
when the server is gone.

`GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` come from Google Cloud Console:
[`google-cloud-setup.html`](./google-cloud-setup.html) is the step-by-step canvas
for that, including the exact redirect URIs this deployment must register.

---

## 7. TLS certificates

### First issuance — standalone

Nginx is not running yet, so certbot can take port 80 for itself:

```bash
certbot certonly --standalone --non-interactive --agree-tos \
    -d marketing.example.com -m admin@marketing.example.com
```

Certificates land in `/etc/letsencrypt/live/marketing.example.com/`, which
`docker-compose.prod.yml` mounts read-only into nginx.

`docker/nginx/default.prod.conf.template` is an nginx *template*: the official
image runs `envsubst` over it at start-up and substitutes `${APP_DOMAIN}`. That
is why the domain is a compose variable and not hardcoded.

### Renewal

`certbot.timer` renews automatically. The one thing it cannot do on its own is
tell nginx to pick up the new file, so `setup-production.sh` installs a deploy
hook:

```sh
# /etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh
docker exec marketing-ai-nginx nginx -s reload || true
```

Test the whole path without spending a rate-limit:

```bash
certbot renew --dry-run
```

If renewal fails because port 80 is busy, switch that domain to the webroot
challenge — the nginx config already serves `/.well-known/acme-challenge/` from
the `certbot-webroot` volume:

```bash
certbot certonly --webroot -w /var/lib/docker/volumes/marketing-ai_certbot-webroot/_data \
    -d marketing.example.com
```

---

## 8. Automatic deployment with GitHub Actions

Two workflows. Tests belong to the pull request; deploying belongs to the merge:

- `.github/workflows/ci.yml` — runs on every **pull request**: Pint, PHPUnit and a
  production frontend build. It is also `workflow_call`-able, which is how the
  deploy reuses it.
- `.github/workflows/deploy.yml` — runs on every **push to `main`**, so in practice
  on the merge. Its first job calls `ci.yml` on the merge commit; only if that
  passes does the second job SSH into the VPS and run `scripts/deploy.sh`.

The suite runs twice on purpose. The tree that merges is not always the tree the
pull request tested — a semantic conflict between two green branches is green
twice and broken once merged — and it is the merged tree that ships.

A red build never reaches the server: the SSH job declares `needs: test`.
`concurrency: production-deploy` means two merges in quick succession queue
instead of racing.

### 8.1 Create the Actions SSH key

Do **not** reuse the deploy key from step 8 of the setup — that one is the
server's identity towards GitHub; this one is GitHub's identity towards the
server. Different direction, different key.

On your laptop:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/marketing_ai_deploy -N "" -C "github-actions"
```

Install the public half on the server:

```bash
ssh-copy-id -i ~/.ssh/marketing_ai_deploy.pub root@YOUR_SERVER_IP
# or, by hand:
cat ~/.ssh/marketing_ai_deploy.pub | ssh root@YOUR_SERVER_IP 'cat >> ~/.ssh/authorized_keys'
```

Verify before wiring it into CI — debugging SSH through Actions logs is misery:

```bash
ssh -i ~/.ssh/marketing_ai_deploy root@YOUR_SERVER_IP 'echo ok'
```

### 8.2 Add the repository secrets

`https://github.com/maalzates/marketing-ai-manager/settings/secrets/actions`

| Secret | Value |
|---|---|
| `SSH_HOST` | the VPS IP or hostname |
| `SSH_USERNAME` | `root` (or your deploy user) |
| `SSH_PRIVATE_KEY` | the **entire** contents of `~/.ssh/marketing_ai_deploy`, including the `-----BEGIN`/`-----END` lines |
| `SSH_PORT` | `22` |

`deploy.yml` declares `environment: production`, so you can additionally require
a manual approval under Settings → Environments → production → Required
reviewers. Recommended once the app has real users.

### 8.3 First run

```bash
git commit --allow-empty -m "chore: trigger deploy"
git push origin main
```

Pushing straight to `main` is what a merge does as far as Actions is concerned, so
this triggers the deploy the same way a merged pull request would.

Watch it under the Actions tab. On success the deploy job prints the deployed
commit hash. You can also trigger it by hand: Actions → *Deploy to production* →
**Run workflow**.

---

## 9. What `deploy.sh` actually does

```
1. git fetch + git reset --hard origin/main   ← survives a force-push
2. docker compose up -d --build               ← no-op when the Dockerfile is unchanged
3. wait for MySQL to answer mysqladmin ping
4. composer install --no-dev --optimize-autoloader
5. npm ci && npm run build   (inside a throwaway node:22-alpine container)
6. php artisan migrate --force
7. php artisan db:seed --force               ← roles + knowledge entries, idempotent
8. optimize:clear, then config:cache / route:cache / view:cache / event:cache
9. queue:restart + restart the queue and scheduler containers
10. curl https://APP_DOMAIN/up  ×10 with backoff  → non-zero exit if it never answers
```

Points worth understanding:

- **`reset --hard`, not `pull`.** A rebase or force-push on `main` would leave a
  `pull` stuck on a merge conflict at 2 a.m. Reset always converges.
- **Seeding is part of the deploy, not a one-off.** The two roles and the knowledge
  entries are reference data, not sample data: with an empty `roles` table the very
  first Google login dies on `Role not found.`, and the onboarding wizard renders
  without its guides. Every seeder is `firstOrCreate`, so re-running costs nothing.
- **Caches are cleared before being rebuilt.** `config:cache` on top of a stale
  cache reads the old values and bakes them in again.
- **Workers must be restarted.** `queue:work` holds the old code in memory for
  the life of the process; without `queue:restart` a deploy ships new code to the
  web tier and old code to the queue tier.
- **Assets are built in a container.** The server never needs a Node toolchain,
  and a Node version bump is a one-line change here rather than a server change.
- **The health check is the gate.** If `/up` never answers, the script exits
  non-zero and the Actions run goes red. It does not roll back for you.

### Deploying by hand

```bash
ssh root@YOUR_SERVER_IP
cd /var/www/marketing-ai-manager
./scripts/deploy.sh
```

---

## 10. Rollback

There is no automatic rollback. Do it explicitly:

```bash
cd /var/www/marketing-ai-manager

git log --oneline -10                 # find the last good commit
git reset --hard <good-sha>

docker compose --env-file .env.docker -f docker-compose.prod.yml up -d --build
docker run --rm -v "$PWD/src:/app" -w /app node:22-alpine sh -c "npm ci && npm run build"
docker compose --env-file .env.docker -f docker-compose.prod.yml exec -T app php artisan optimize:clear
docker compose --env-file .env.docker -f docker-compose.prod.yml exec -T app php artisan config:cache
```

**Migrations do not roll back with the code.** If the bad deploy migrated the
schema, decide deliberately: `migrate:rollback --step=1` if the down migration
is safe, or restore last night's dump. This is why destructive migrations
(dropping a column, renaming a table) should ship in two deploys — add the new
shape first, remove the old one a release later.

---

## 11. Logs and observability

```bash
cd /var/www/marketing-ai-manager
C="docker compose --env-file .env.docker -f docker-compose.prod.yml"

$C ps                              # what is running
$C logs -f --tail=100 app          # php-fpm
$C logs -f --tail=100 nginx        # access + error
$C exec app tail -f storage/logs/laravel.log
docker stats --no-stream           # CPU / memory per container
```

The Loki + Promtail + Grafana stack is optional and off by default:

```bash
$C --profile observability up -d
```

Grafana binds to `127.0.0.1:3000` only — it is not exposed to the internet.
Reach it through an SSH tunnel:

```bash
ssh -L 3000:127.0.0.1:3000 root@YOUR_SERVER_IP
# then open http://localhost:3000
```

Loki retains 30 days (`retention_period: 720h` in
`docker/observability/loki-config.yml`).

---

## 12. Backups

`setup-production.sh` installs `/etc/cron.daily/marketing-ai-db-backup`:
a `mysqldump --single-transaction`, gzipped into `/var/backups/marketing-ai/`,
pruned after 14 days.

Verify it works — an untested backup is not a backup:

```bash
/etc/cron.daily/marketing-ai-db-backup
ls -lh /var/backups/marketing-ai/
```

Restore:

```bash
cd /var/www/marketing-ai-manager
set -a; source .env.docker; set +a
gunzip < /var/backups/marketing-ai/db-2026-08-22.sql.gz \
  | docker compose --env-file .env.docker -f docker-compose.prod.yml exec -T db \
    mysql -u root -p"${MYSQL_ROOT_PASSWORD}" "${MYSQL_DATABASE}"
```

Backups sitting on the same disk as the database protect you from a bad
migration, not from losing the server. Ship them off-box (`rclone`, `restic`,
provider snapshots) before you have real data.

---

## 13. Troubleshooting

| Symptom | Likely cause | Check |
|---|---|---|
| 502 Bad Gateway | php-fpm is down or crashed on boot | `$C ps`, `$C logs app` |
| 500 on every page, blank log | `APP_KEY` empty, or `storage/` not writable | `grep APP_KEY src/.env`; `$C exec app ls -ld storage/logs` |
| "Access denied for user" | `src/.env` password ≠ the MySQL user's actual password | See the box in section 6 |
| Config change has no effect | `config:cache` still holds the old values | `$C exec app php artisan optimize:clear`, then re-cache |
| CSS/JS 404 | assets were never built, or `public/build` is missing | `ls src/public/build/manifest.json`; re-run the npm step |
| Old JS still served | browser or Cloudflare cache | Vite hashes filenames — purge Cloudflare, hard-reload |
| `git reset` fails on the server | someone edited files in place | `git status`; commit it properly or `git checkout -- .` |
| Certificate expired | renewal hook never reloaded nginx | `certbot renew --dry-run`; `docker exec marketing-ai-nginx nginx -s reload` |
| Deploy hangs on `npm ci` | out of memory | `free -h` — the swap file from step 6 is not optional |
| Queue jobs run old code | `queue:restart` skipped | `$C restart queue` |
| Google: "Missing required parameter: client_id" | `GOOGLE_CLIENT_ID` empty in `src/.env` on the server | `curl -s https://YOUR_DOMAIN/api/v1/auth/google/redirect` — an empty `client_id=` in the URL says it; fill it, then `$C exec app php artisan config:cache` |
| Google: `redirect_uri_mismatch` | the console does not have the URI the app derives from `APP_URL` | Compare the `redirect_uri` in that same URL against the console's authorised list |

Full reset (**destroys the database** — take a dump first):

```bash
$C down -v
$C up -d --build
./scripts/deploy.sh
```

---

## 14. Security checklist

Run through this once the app is live:

- [ ] `APP_DEBUG=false` and `APP_ENV=production` in `src/.env`
- [ ] `.env.docker` and `src/.env` are not in git (`git status` shows nothing)
- [ ] MySQL is bound to `127.0.0.1:3306`, not `0.0.0.0`
- [ ] Grafana is bound to `127.0.0.1:3000`, reached only over an SSH tunnel
- [ ] UFW allows only 22, 80, 443 (`ufw status`)
- [ ] fail2ban is running (`systemctl status fail2ban`)
- [ ] SSH is key-only: `PasswordAuthentication no` in `/etc/ssh/sshd_config`
- [ ] Root login disabled once a sudo-capable deploy user exists
- [ ] Unattended security upgrades enabled
- [ ] The GitHub deploy key is read-only
- [ ] The Actions SSH key is dedicated to Actions and used nowhere else
- [ ] Backups are copied off the box
- [ ] The `production` environment in GitHub requires a reviewer (once you have users)
