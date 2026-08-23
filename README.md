# Marketing AI Manager

Laravel 13 API + Vue 3 SPA, running on Docker, deployed to a single VPS with
automatic deploys from `main`.

## Quick start

```bash
git clone git@github.com:maalzates/marketing-ai-manager.git
cd marketing-ai-manager
make init
```

`make init` builds the images, installs PHP and JS dependencies, generates the
app key, runs the migrations and starts everything.

| Service | URL |
|---|---|
| Application | http://localhost:8080 |
| Vite dev server | http://localhost:5173 |
| Mailpit | http://localhost:8025 |
| MySQL | `127.0.0.1:3307` |
| Redis | `127.0.0.1:6380` |
| Grafana (`make obs-up`) | http://localhost:3000 — admin/admin |

Ports are overridable: `HTTP_PORT=9000 make up`.

## Commands

`make` with no arguments lists every target. The ones you will use:

```bash
make up / make down       # start / stop the stack
make shell                # bash inside the php-fpm container
make test                 # PHPUnit — sqlite :memory:, no services needed
make pint-fix             # fix code style
make migrate              # run pending migrations
make logs-app             # tail storage/logs/laravel.log
make artisan CMD="route:list"
```

## Layout

```
src/          the Laravel application (app/Modules/, resources/js/, routes/, tests/)
docker/       nginx, php.ini, mysql init, observability configs
scripts/      deploy.sh, setup-production.sh
spec/         one folder per unit of work (see CLAUDE.md)
docs/         architecture
guidelines/   deployment guide
.ai/          binding standards for AI agents
```

## Stack

Laravel 13 · PHP 8.4 · MySQL 8.4 · Redis 7 · Vue 3 · Vue Router · Pinia ·
TailwindCSS 4 · Vite · nginx · Docker Compose

## Deployment

Push to `main`. CI runs Pint, PHPUnit and a production frontend build; if it
passes, the deploy workflow SSHes into the VPS and runs `scripts/deploy.sh`.

Setting up a new server, the environment files, TLS, GitHub secrets, rollback,
backups and troubleshooting are all in
[`guidelines/DEPLOYMENT-GUIDE.md`](./guidelines/DEPLOYMENT-GUIDE.md).

## Backend architecture

Modular monolith with DDD: every backend class lives in `src/app/Modules/{Module}/`,
split into `Application/`, `Domain/`, `Infrastructure/` and `Presentation/`.
`Modules/Core/` holds the shared base — the JSON envelope, the domain exception
hierarchy, and the Guzzle factory every external API client is built from.

Patterns and templates: the `marketing-backend-ddd` skill. Rationale and the
new-module checklist: [`guidelines/backend_guidelines.md`](./guidelines/backend_guidelines.md).

## How we work

Spec-driven: every change starts with a `spec/YYYY-MM-DD-{case-name}/context.md`,
then a `plan.md`, and ends with a `guide.md`. The workflow, the phases and the
project agents are described in [`CLAUDE.md`](./CLAUDE.md); the binding coding
standards are in [`.ai/ai-guidelines.md`](./.ai/ai-guidelines.md).
