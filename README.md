# Marketing AI Manager

Laravel 13 API + Vue 3 SPA, running on Docker, deployed to a single VPS with
automatic deploys from `main`.

## Quick start

```bash
git clone git@github.com:maalzates/marketing-ai-manager.git
cd marketing-ai-manager
make up
```

`make up` builds the images, installs PHP and JS dependencies, generates the app
key, creates both schemas, runs the migrations and starts everything. It is safe
to re-run; use `make start` for a plain resume.

| Service | URL |
|---|---|
| Application | http://localhost:8080 |
| Vite dev server | http://localhost:5173 |
| Mailpit | http://localhost:8025 |
| MySQL | `127.0.0.1:3307` |
| Redis | `127.0.0.1:6380` |
| Grafana (optional profile) | http://localhost:3000 — admin/admin |

Ports are overridable: `HTTP_PORT=9000 make up`.

## Commands

```bash
make            # list the targets
make up         # build, install, migrate and start everything (safe to re-run)
make start      # resume after `make stop`
make stop       # stop the containers
make down       # stop and remove them (the database volume survives)
make exec       # bash inside the php-fpm container
make logs       # tail every container
make test       # the feature suite; `make test FILTER=CampaignTest` narrows it
make pint       # fix PHP code style
make artisan CMD="route:list"   # anything else
```

Ten targets, deliberately. Everything the Makefile does not cover is reachable
with `make exec` or `make artisan CMD="..."`.

## Layout

```
src/          the Laravel application (app/Modules/, resources/js/, routes/, tests/)
docker/       nginx, php.ini, mysql init, observability configs
scripts/      deploy.sh, setup-production.sh
spec/         one folder per unit of work (see CLAUDE.md)
docs/         deployment guide + the two visual canvases
.ai/          binding standards for AI agents (architecture, backend, tests)
```

## Stack

Laravel 13 · PHP 8.4 · MySQL 8.4 · Redis 7 · Vue 3 · Vue Router · Pinia ·
TailwindCSS 4 · Vite · nginx · Docker Compose

## Deployment

Push to `main`. CI runs Pint, PHPUnit and a production frontend build; if it
passes, the deploy workflow SSHes into the VPS and runs `scripts/deploy.sh`.

Setting up a new server, the environment files, TLS, GitHub secrets, rollback,
backups and troubleshooting are all in
[`docs/deployment.md`](./docs/deployment.md).

## Visual overview

Two self-contained HTML canvases, kept current as the last step of every change:

- [`docs/project-map.html`](./docs/project-map.html) — repo layout, stack,
  container topology for dev and production, ports, environment files, the
  deploy path, the make targets.
- [`docs/system-flows.html`](./docs/system-flows.html) — the doors into the
  application, the layers, a request end to end, errors and logging, outbound
  API calls and credentials, the proposal/approval invariant, account
  isolation, testing, and how work gets done.

Open them straight from the filesystem; no build step, no dependencies.

## Backend architecture

Modular monolith with DDD: every backend class lives in `src/app/Modules/{Module}/`,
split into `Application/`, `Domain/`, `Infrastructure/` and `Presentation/`.
`Modules/Core/` holds the shared base — the JSON envelope, the domain exception
hierarchy, and the Guzzle factory every external API client is built from.

Patterns and templates: the `marketing-backend-ddd` skill. Rationale and the
new-module checklist: [`.ai/backend-guidelines.md`](./.ai/backend-guidelines.md).

## How we work

Spec-driven: every change starts with a `spec/YYYY-MM-DD-{case-name}/context.md`,
then a `plan.md`, and ends with a `guide.md`. The workflow, the phases and the
project agents are described in [`CLAUDE.md`](./CLAUDE.md); the binding coding
standards are in [`.ai/ai-guidelines.md`](./.ai/ai-guidelines.md).
