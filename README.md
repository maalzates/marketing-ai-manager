# Marketing AI Manager

A marketing manager for one person. You describe your brand, write a strategy, and
state each experiment's expected result **before** it runs. The application then
watches the real numbers — Instagram, Facebook Ads, your competitors — and when
something drifts it *proposes* an action: close this experiment, move that budget,
launch this campaign.

It never acts on its own. Every mutation waits for a human to accept it, and that
rule is enforced by the architecture rather than by a prompt: there is no code path
from the chat assistant, or from a scheduled job, to a Service that executes
anything.

Laravel 13 API + Vue 3 SPA, running on Docker, deployed to a single VPS with
automatic deploys from `main`.

**New here? Read [`SETUP.md`](./SETUP.md).** It takes an empty machine to a working
application: the external consoles, both environment files, bring-up, first login
and onboarding. The quick start below is only the container half of it.

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

The application will start without a single provider credential, and will not do
much: signing in needs the Google OAuth client from `src/.env`, and everything else
needs the keys each user enters through onboarding. `SETUP.md` walks both.

## Two kinds of credentials

Confusing these is the most likely way to get stuck, so it is the first thing in
`SETUP.md` and the first thing here.

| | **Platform credentials** | **User credentials** |
|---|---|---|
| Identify | the application | a person using it |
| Set by | whoever runs the deployment, once | each user, in the app |
| Live in | `src/.env` | the `integrations` table, encrypted per account |
| Examples | Google OAuth client id/secret, Meta app id/secret, the redirect URIs, `META_GRAPH_VERSION`, `ADMIN_EMAILS`, `APP_KEY`, database and Redis | Apify token, Anthropic / OpenAI / Gemini API key, the Meta and Google OAuth connections |

**A user's key never goes near `.env`.** There is no fallback key, no shared
account key, no "the admin's key when the user has none". Keys are resolved per
account at the moment they are used and discarded — never cached in a singleton,
a static property or shared container state, because a singleton would serve every
account with one user's key. This is also why the project does not use
`laravel/ai`: it can only take a key from `config`, where it shows up in
`php artisan about` and in any error-page config dump.

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
SETUP.md      empty machine → working application
```

## The modules

Every backend class lives in one of these. A module owns one slice of the domain
and exposes the rest of the application exactly one thing: a Service.

| Module | Owns |
|---|---|
| `Core` | The shared base: JSON envelope, exception hierarchy, Guzzle factory, account context, the two middlewares, the chat tool registry |
| `Auth` · `Accounts` · `Admin` | Google sign-in without passwords, accounts and roles, the admin surface (users, roles, application API keys, global settings) |
| `Onboarding` · `Settings` · `Knowledge` | The four-step wizard, the cascading settings registry (strategy → account → global → declared default), the editable domain knowledge and glossary |
| `Integrations` | Every user credential: encrypted storage, live verification, the Google and Meta OAuth flows, silent refresh, the daily health check |
| `Ai` | One client per LLM provider, model routing per task, prompt assembly, the token budget, the analysis cache |
| `Brands` · `Strategies` · `Experiments` | The brand profile, the strategy and its budget, and the experiment — including the rule that nothing is created without a written expected result and an end date |
| `Proposals` | The approval invariant. Anything may propose; only the human-accept endpoint executes |
| `Chat` | The assistant and its tool loop. Read tools answer, mutation tools propose |
| `Competitors` | Apify scraping, pattern analysis, comment mining, sentiment, insights |
| `Content` · `Assets` | Scripts, the calendar, organic publishing with retries and a manual fallback; the Drive-backed asset library and the signed media stream |
| `Campaigns` | Meta Ads: campaign, ad set, ad, creative upload, sandbox mode, metric sync |
| `Reporting` | The daily guardián, experiment verdicts, reports |
| `Audit` | Action log, LLM spend ledger, Apify spend ledger, secret masking |

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

- [`docs/project-map.html`](./docs/project-map.html) — repo layout, the modules,
  stack, container topology for dev and production, ports, environment files, the
  deploy path, the make targets.
- [`docs/system-flows.html`](./docs/system-flows.html) — the doors into the
  application, the layers, a request end to end, errors and logging, outbound
  API calls and credentials, the proposal/approval invariant, account
  isolation, testing, and how work gets done.

Open them straight from the filesystem; no build step, no dependencies.

## Backend architecture

Modular monolith with DDD: every backend class lives in `src/app/Modules/{Module}/`,
split into `Application/`, `Domain/`, `Infrastructure/` and `Presentation/`. Every
JSON endpoint lives under `/api/v1`; `/api/health` stays unversioned because a
liveness probe must not move with the API, and `/media/{token}` sits outside `/api`
entirely because Instagram's publishing API pulls the bytes and its fetcher carries
no token.

Patterns and templates: the `marketing-backend-ddd` skill. Rationale and the
new-module checklist: [`.ai/backend-guidelines.md`](./.ai/backend-guidelines.md).

## Testing

Feature tests only — every test enters through a real route, job or command and
runs against MySQL, not sqlite, so repository tests exercise the JSON columns,
enums and collations that ship. Nothing this repo owns is ever mocked; only what
leaves the machine is faked. [`.ai/test-guidelines.md`](./.ai/test-guidelines.md)
has the rules, including the separate schemas concurrent suites need.

## How we work

Spec-driven: every change starts with a `spec/YYYY-MM-DD-{case-name}/context.md`,
then a `plan.md`, and ends with a `guide.md`. The workflow, the phases and the
project agents are described in [`CLAUDE.md`](./CLAUDE.md); the binding coding
standards are in [`.ai/ai-guidelines.md`](./.ai/ai-guidelines.md).
