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
7. [TLS and Cloudflare](#7-tls-and-cloudflare)
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
                    visitor ──HTTPS:443──► Cloudflare
                                               │ HTTP :80, in the clear
                                               ▼
┌──────────────────────── VPS (Ubuntu 24.04) ────────────────────────┐
│                                                                    │
│  nginx  :80 only, no certificate. UFW allows :80 from              │
│    │    Cloudflare ranges alone; :443 is closed                    │
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
| A domain on Cloudflare | Nameservers pointed at Cloudflare. This is not optional: TLS terminates there, so without Cloudflare the site is served over plain HTTP. |
| A GitHub repo | `maalzates/marketing-ai-manager`, with Actions enabled. |
| API keys | `ANTHROPIC_API_KEY`, plus whatever the app grows into. |

Nothing needs to be installed on the server by hand — `setup-production.sh`
brings Docker with it. Node is **not** required on the server: assets are built
in a throwaway `node:22-alpine` container.

---

## 3. DNS first

DNS goes before everything else, because the origin never sees a hostname the
visitor typed — it sees whatever Cloudflare forwards, and it serves by name.

Create the records in Cloudflare:

| Type | Name | Value | Proxy |
|---|---|---|---|
| A | `@` | your VPS IPv4 | **Proxied** (orange cloud) |
| A | `www` | your VPS IPv4 | **Proxied** (orange cloud) |

Then set **SSL/TLS → Overview → Flexible**. That is the mode this stack is built
for: HTTPS between the visitor and Cloudflare, plain HTTP between Cloudflare and
the origin. Section 7 explains what that costs and what pays for it.

Verify from your laptop:

```bash
dig +short marketing.example.com
# prints Cloudflare IPs (104.x / 172.6x), NOT the VPS IP — that is correct
# when the record is proxied
```

The VPS IP is deliberately not what resolves. If `dig` prints the VPS IP, the
record is grey-clouded and nothing in section 7 applies: the origin would be
answering the internet directly, in the clear.

**Do not set Full or Full (strict).** The origin has no certificate and no `:443`
listener. Full makes Cloudflare try HTTPS to the origin and every request fails;
"Off" strips TLS from the visitor. Flexible is the only mode that matches this
configuration.

DNS propagation is usually seconds, occasionally an hour.

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
| 4 | UFW: deny inbound, allow 22 only. Port 80 is opened per Cloudflare range in step 11; 443 stays closed | nothing |
| 5 | fail2ban enabled | nothing |
| 6 | 4 GB swap file | nothing |
| 7 | Unattended security upgrades | nothing |
| 8 | Generates an ed25519 key, prints the public half | **add it as a deploy key on GitHub**, then press Enter |
| 9 | Clones the repo to `/var/www/marketing-ai-manager` | nothing |
| 10 | Creates `.env.docker` (random passwords) and `src/.env` | **set `APP_DOMAIN`, then the Laravel secrets**, press Enter between the two |
| 11 | Installs the Cloudflare range refresher (nginx `real_ip` + the UFW allowlist for :80) and the fail2ban GitHub Actions exemption, then enables both systemd timers | confirm the records are proxied and SSL/TLS is Flexible |
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
apt-get install -y ca-certificates curl git gnupg lsb-release unzip ufw fail2ban cron jq dnsutils

# 5.2 Docker
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
chmod a+r /etc/apt/keyrings/docker.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" > /etc/apt/sources.list.d/docker.list
apt-get update
apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
systemctl enable --now docker

# 5.3 Firewall — SSH from anywhere, :80 only from Cloudflare, :443 never
#      The per-range rules for :80 are written by the refresher in section 7.
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp
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

## 7. TLS and Cloudflare

**There is no certificate on this server, and no certbot.** TLS terminates at
Cloudflare in **Flexible** mode: the visitor talks HTTPS to Cloudflare,
Cloudflare talks plain HTTP to the origin on port 80. Issuing a Let's Encrypt
certificate here would be dead weight — Cloudflare would never present it.

### What that costs, and what pays for it

The hop from Cloudflare to the box is **unencrypted**. That is what Flexible
means. It is only acceptable because port 80 is reachable **from Cloudflare's
ranges alone**:

```bash
ufw status
# 22/tcp   ALLOW  Anywhere
# 80/tcp   ALLOW  173.245.48.0/20        <- one rule per Cloudflare range
# 80/tcp   ALLOW  103.21.244.0/22
# ...
# 443 does not appear: nothing listens there
```

If that restriction is ever lifted, anyone who finds the origin IP reads the
traffic in the clear — and, worse, can forge `CF-Connecting-IP`, because nginx
and Laravel are both configured to trust it (`src/bootstrap/app.php`). The
firewall rule and trusting the proxy are **one decision, not two**.

### The two things nginx needs from the host

`docker/nginx/default.prod.conf.template` is an nginx *template*: the official
image runs `envsubst` over it at start-up and substitutes `${APP_DOMAIN}`. That
is why the domain is a compose variable and not hardcoded.

Its first directive is `include /etc/nginx/cloudflare/real-ip.conf`, and
`docker-compose.prod.yml` mounts `/etc/nginx/cloudflare` from the host. **If that
file does not exist, nginx refuses to start.** It is generated by the refresher
below — which is why the refresher runs once during setup, before the stack comes
up, and not only on its timer.

Without it, every log line, every rate limit and every action-log entry would
record Cloudflare's IP instead of the visitor's.

### The refresher

`/usr/local/sbin/cloudflare-ranges-refresh` does two jobs from one source of
truth — <https://www.cloudflare.com/ips-v4> and `ips-v6`:

1. Writes `/etc/nginx/cloudflare/real-ip.conf`: one `set_real_ip_from` per range,
   plus `real_ip_header CF-Connecting-IP` and `real_ip_recursive on`.
2. **Rebuilds** the UFW allowlist for `:80` from scratch, so a range Cloudflare
   retires stops being allowed. Deleting first is the point — appending would
   only ever grow the list.

It refuses to install a list shorter than 5 IPv4 entries: a failed fetch that
left the file empty would lock Cloudflare out and take the site down.

`cloudflare-ranges-refresh.timer` runs it weekly (`RandomizedDelaySec=2h`,
`Persistent=true`) and its service reloads nginx afterwards if the container is
running.

```bash
systemctl list-timers cloudflare-ranges-refresh.timer
/usr/local/sbin/cloudflare-ranges-refresh    # safe to run by hand, idempotent
```

### The other timer: keeping fail2ban off the CI runner

Same section of `setup-production.sh`, different problem. fail2ban bans IPs after
failed SSH attempts, and **GitHub Actions runners come from ranges that change
without notice**. When one gets banned the deploy fails weeks later with nothing
in the Actions log but a timeout.

- `/usr/local/sbin/github-ranges-refresh` caches `.actions[]` from
  `https://api.github.com/meta` into `/etc/fail2ban/github-actions-ranges.txt`,
  and refuses to install fewer than 100 entries.
- `/usr/local/sbin/fail2ban-ignore-github` is the `ignorecommand`: it checks one
  IP against that **cached** file. Cached and not live on purpose — fail2ban
  calls it for every candidate, and a network round trip per attempt would be its
  own denial of service. `ignoreip` cannot carry the list: GitHub publishes
  ~7000 ranges.
- The `sshd` jail runs `mode = normal`, deliberately **not** `aggressive`:
  aggressive bans on `Connection closed by authenticating user`, which is exactly
  what a CI runner reconnecting looks like.
- `github-ranges-refresh.timer` runs daily, `RandomizedDelaySec=1h`.

```bash
/usr/local/sbin/fail2ban-ignore-github 4.175.114.51 ; echo $?   # 0 — exempt
/usr/local/sbin/fail2ban-ignore-github 8.8.8.8 ; echo $?        # 1 — not exempt
```

### Verifying the whole path

```bash
curl -sI https://marketing.example.com | head -1        # 200, from anywhere
curl -sI http://VPS_IP -m 5                             # must time out: not a Cloudflare IP
ss -lntp | grep ':443'                                  # no output — correct
docker exec marketing-ai-nginx nginx -t                 # syntax OK, real-ip.conf found
```

`APP_URL` in `src/.env` stays on **`https://`** even though the origin speaks
HTTP. It is what the visitor sees, and it is what Laravel must generate; nginx
passes `HTTPS=on` to php-fpm when `X-Forwarded-Proto: https` arrives, so
`url()` and `route()` build `https://` links and the browser does not block them
as mixed content.

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
   then wait for MySQL to answer mysqladmin ping
3. composer install --no-dev --optimize-autoloader
4. npm ci && npm run build   (inside a throwaway node:22-alpine container)
5. chown -R www-data storage bootstrap/cache  ← a fresh clone lands as root
6. php artisan migrate --force
7. php artisan db:seed --force               ← roles + knowledge entries, idempotent
8. optimize:clear, then config:cache / route:cache / view:cache / event:cache
9. queue:restart + restart app, queue, scheduler — then nginx, last
10. curl -H "Host: APP_DOMAIN" http://127.0.0.1/up  ×10 with backoff
    → non-zero exit if it never answers
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
- **nginx is restarted last, and that is not cosmetic.** It resolves `app` once, when
  it loads its config. A recreated app container can come back on a different address
  — the one nginx cached may by then belong to the scheduler — and every request
  answers 502 with php-fpm perfectly healthy and nothing wrong in its log.
- **Workers must be restarted.** `queue:work` holds the old code in memory for
  the life of the process; without `queue:restart` a deploy ships new code to the
  web tier and old code to the queue tier.
- **Assets are built in a container.** The server never needs a Node toolchain,
  and a Node version bump is a one-line change here rather than a server change.
- **Step 5 is not optional, and its absence is invisible.** php-fpm runs as
  `www-data`, but a fresh clone and anything git writes land as `root`. Laravel
  then cannot write its own log, so the failure is a 500 in nginx, a 500 in
  php-fpm and an empty `storage/logs/`.
- **The health check goes over plain HTTP, against `127.0.0.1`, with an explicit
  `Host` header.** There is no `:443` listener to check against, and nginx serves
  by name — without the header the request does not match the server block.
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
| nginx exits at boot, `open() "/etc/nginx/cloudflare/real-ip.conf" failed` | the refresher never ran, so the file the template includes does not exist | `/usr/local/sbin/cloudflare-ranges-refresh`, then `$C restart nginx` |
| `ERR_TOO_MANY_REDIRECTS`, or 521/522 from Cloudflare | SSL/TLS mode is Full or Full (strict); the origin has no `:443` | Cloudflare → SSL/TLS → Overview → **Flexible** |
| Every visitor logged with a Cloudflare IP | `real-ip.conf` is stale or empty | `head -3 /etc/nginx/cloudflare/real-ip.conf`; re-run the refresher |
| The site 403s or times out for real users | UFW allowlist rebuilt from a bad fetch, or a new Cloudflare range | `ufw status \| grep -c 80/tcp` — expect ~20; re-run the refresher |
| Deploys start timing out after weeks of working | fail2ban banned the Actions runner | `fail2ban-client status sshd`; `/usr/local/sbin/github-ranges-refresh` |
| Deploy hangs on `npm ci` | out of memory | `free -h` — the swap file from step 6 is not optional |
| Queue jobs run old code | `queue:restart` skipped | `$C restart queue` |
| 502 on everything, `app` healthy | nginx cached the old address of the `app` container | `$C logs nginx` shows `connect() failed … upstream: fastcgi://172.18.0.x`; compare with `docker inspect`, then `$C restart nginx` |
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
- [ ] UFW allows 22 from anywhere and **80 only from Cloudflare's ranges** (`ufw status`)
- [ ] **443 is closed** and nothing listens there (`ss -lntp | grep ':443'` prints nothing)
- [ ] Cloudflare SSL/TLS mode is **Flexible** and both DNS records are proxied
- [ ] `cloudflare-ranges-refresh.timer` and `github-ranges-refresh.timer` are enabled (`systemctl list-timers`)
- [ ] The origin IP is not reachable on 80 from a non-Cloudflare address (`curl -sI http://VPS_IP -m 5` times out)
- [ ] fail2ban is running (`systemctl status fail2ban`)
- [ ] SSH is key-only: `PasswordAuthentication no` in `/etc/ssh/sshd_config`
- [ ] Root login disabled once a sudo-capable deploy user exists
- [ ] Unattended security upgrades enabled
- [ ] The GitHub deploy key is read-only
- [ ] The Actions SSH key is dedicated to Actions and used nowhere else
- [ ] Backups are copied off the box
- [ ] The `production` environment in GitHub requires a reviewer (once you have users)
