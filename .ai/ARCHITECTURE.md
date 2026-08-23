# Architecture

One repository, one deployable: a Laravel 13 API + a Vue 3 SPA served by the same
nginx, wrapped in Docker.

```
marketing-ai-manager/
├── src/                     # the Laravel application root
│   ├── app/
│   │   ├── Modules/         # feature code (DDD layout, see below)
│   │   └── Providers/
│   ├── bootstrap/app.php    # routing, middleware, exception rendering
│   ├── database/migrations/
│   ├── resources/js/        # the Vue SPA
│   ├── resources/views/app.blade.php   # the single blade entrypoint
│   ├── routes/api.php       # every JSON endpoint
│   ├── routes/web.php       # catch-all that renders app.blade.php
│   └── tests/Feature/      # feature tests only; there is no tests/Unit
├── docker/                  # nginx, php.ini, mysql init, observability configs
├── scripts/                 # deploy.sh, setup-production.sh
├── docker-compose.yml       # development stack
├── docker-compose.prod.yml  # production stack
└── Makefile                 # every command a developer needs
```

## Request lifecycle

```
browser
  → nginx (:80 dev, :443 prod)
      → /api/*        → php-fpm → routes/api.php  → Controller → Service → Repository → Model
      → anything else → php-fpm → routes/web.php  → app.blade.php → Vue Router takes over
```

`/up` is Laravel's built-in health endpoint and is what `deploy.sh` smoke-tests.
`/api/health` is the application's own liveness probe.

## One core, many doors

HTTP is not the only way in. The chat assistant's tools, scheduled jobs and (in
future) an MCP server are all **driving adapters**: thin doors that translate
their own input into a DTO and call the same Service.

```
HTTP request        Chat tool_use         Scheduled job        MCP call (future)
     ↓                    ↓                     ↓                    ↓
FormRequest          Tool class            Job class            Mcp handler
     ↓                    ↓                     ↓                    ↓
Controller ─────────────→ Service ←────────────┴────────────────────┘
                             ↓
                     Repository / Client
                             ↓
                      Model / ApiClient
```

Three consequences, all load-bearing:

- **Doors hold no business logic.** Validation at the boundary, DTO, delegate.
- **Invariants live in Services and Domain**, so no door can bypass them.
- **Mutations proposed by the LLM are not executions.** A mutation Tool calls a
  `Propose*Service` that persists a `Proposal`. The executing Service is
  reachable only from the human approval endpoint
  (`POST /api/v1/proposals/{id}/accept`), which the LLM does not know about.
  There is no code path from a Tool to an executing Service — the architecture
  enforces the approval rule, not the prompt.

## Backend — modular DDD

**Every backend class lives in a module.** `app/Http/`, `app/Models/` and a flat
`app/Services/` are not where feature code goes — the first holds only Laravel's
base `Controller`, the second only `User` until the auth module claims it, the
third does not exist.

A module owns one bounded slice of the domain (Campaigns, Competitors, Content,
Youtube, …) and exposes the rest of the app exactly one thing: a Service.

```
app/Modules/{Module}/
├── Application/
│   ├── DTO/                     # readonly data carriers between layers
│   ├── Jobs/                    # queued work owned by this module
│   └── Services/                # readonly, business logic only
├── Domain/
│   ├── Contracts/               # repository and client interfaces
│   ├── Enums/
│   └── Exceptions/              # extend Core's ApiException / ClientException
├── Infrastructure/
│   ├── Persistence/             # Eloquent models
│   ├── Repositories/            # readonly, the only place that talks to the DB
│   └── Clients/                 # external API clients
├── Presentation/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   └── Requests/            # validation + toDTO()
│   ├── Tools/                   # chat assistant tools (driving adapters)
│   └── Jobs/                    # queued and scheduled entry points
└── {Module}ServiceProvider.php  # binds contracts, registers tools
```

Register each provider in `bootstrap/providers.php`. Migrations stay in
`database/migrations/` and factories in `database/factories/` — Laravel loads
those globally.

Full patterns and templates: the `marketing-backend-ddd` skill. Rationale and the
module checklist: [`.ai/backend-guidelines.md`](../.ai/backend-guidelines.md).

### The Core module

`app/Modules/Core/` is the only module that already exists. Everything else
builds on it and never duplicates it.

| Class | Role |
|---|---|
| `Presentation/Http/Controllers/Api/ApiController` | Base controller; exposes `$this->response`. |
| `Presentation/Http/Responses/ApiResponse` | The single JSON envelope. |
| `Presentation/Http/Responses/ExceptionRenderer` | Maps any exception — domain, validation, auth, a router 404 — into that envelope. |
| `Presentation/Http/Requests/RequestHelperTrait` | Typed accessors for validated input. |
| `Presentation/Tools/ToolRegistry` | Maps tool name → Tool class, with its schema. The chat loop and the future MCP adapter both read from it. |
| `Presentation/Tools/ToolAbstract` | Base chat tool: schema, input validation, account context. |
| `Domain/Exceptions/ApiException` | Base domain exception: context, log level, HTTP status. |
| `Domain/Exceptions/ClientException` | An `ApiException` whose message is safe to show the caller. |
| `Domain/Exceptions/ApiCallFailedException` | Raised by `ApiClientAbstract` on any failed outbound call. |
| `Infrastructure/Clients/GuzzleClientFactory` | Builds every outbound client with shared timeouts and headers. |
| `Infrastructure/Clients/ApiClientAbstract` | HTTP verbs, JSON decoding, error translation. |

### Response envelope

Every JSON response has the same two keys, so the frontend never branches on
shape:

```json
{ "result": { "id": "camp_1" }, "errors": [] }
{ "result": [], "errors": { "message": "Campaign not found", "status_code": 404 } }
```

Success bodies come from `ApiResponse`. Error bodies are produced centrally by
`ExceptionRenderer`, wired in `bootstrap/app.php` — including the ones Laravel
raises itself, so a router 404 and a domain exception look the same to the
frontend. A validation failure adds `errors.fields`. A controller never builds an
error response.

### Layering rules

| Layer | May depend on | Must never |
|---|---|---|
| Door (Controller, Tool, Job) | Service, DTO | Touch Eloquent, build queries, catch anything, hold a business rule |
| Service | Repository/client **contracts**, DTO | Know about HTTP; catch exceptions |
| Repository | Model, query builder | Contain business rules |
| Client | Guzzle (via `ApiClientAbstract`) | Leak a Guzzle type outside Infrastructure |
| Model | — | Contain logic beyond relations, casts, scopes |

- Repositories return `Collection | Model | LengthAwarePaginator` — never arrays.
- DTOs, Services and Repositories are `readonly` classes.
- `declare(strict_types=1)` in every file.
- Nothing type-hints a concrete repository; the provider resolves the contract.
- A module may depend on another module's Service, never on its repository,
  model or client.
- **Every tenant-owned table carries `account_id`,** the account travels in the
  DTO, and repositories always filter by it. Tests assert the isolation.

### Errors and logging

`try`/`catch` exists in exactly two places: repositories and API clients. They
attach context to a domain exception and throw it. Everything above lets it
bubble.

**Nobody calls `Log::` by hand.** `ApiException::getLogLevel()` decides the
severity (422/400 → info, other 4xx → warning, 5xx → error) and the handler in
`bootstrap/app.php` writes exactly one structured line with the exception's
context.

The exception *message* is always author-written and may be returned to the
caller. A provider's raw response goes in `context`, which only reaches the logs.
An exception that is not an `ApiException` gets a generic message unless
`APP_DEBUG` is on — a library's message is not ours to publish.

### External API clients and credentials

Outbound integrations are built by `GuzzleClientFactory`. How they are bound
depends on whose credentials they carry:

| Kind | Identifies | Source | Binding |
|---|---|---|---|
| Platform (Google OAuth client, Meta App, DB, Redis) | the application | `config/services.php` reading `env()` | singleton in the module's provider |
| Account / BYOK (Apify key, LLM key, Meta and Google tokens) | the user | encrypted in that account's integrations | a `*ClientFactory` resolving `forAccount($accountId)` per request or job |

An account-scoped client — or its key — is never cached in a singleton, a static
property or shared container state. Resolve per account, use, discard: a
singleton would serve every account with one user's key.

`env()` is never called outside `config/` — production runs on a cached config,
where it returns `null`.

A client names its endpoints as constants, shapes the parameters, and translates
`ApiCallFailedException` into its own domain exception. No caching, no business
rules, no persistence.

### Validation and transformation

Input validation and any reshaping of the payload happen in the FormRequest:
transformation in `prepareForValidation()`, mapping in `toDTO()`. A `toDTO()`
that transforms data is a bug.

## Frontend — SPA layout

```
resources/js/
├── app.js            # createApp + pinia + router
├── bootstrap.js      # the single configured axios instance
├── App.vue
├── router/index.js
├── layouts/          # page chrome
├── pages/            # one component per route
├── components/       # reusable pieces
├── stores/           # Pinia — state + user-facing feedback
└── repositories/     # the only place axios is called
```

Flow: `Component → Store → Repository → axios → /api`.

- `@` is aliased to `resources/js`.
- axios is configured once in `bootstrap.js` (`baseURL: /api`, bearer token from
  `localStorage('access_token')`). Never import raw `axios` elsewhere.
- Repositories unwrap the envelope: `(await axios.get('/x')).data.result`. The
  response interceptor flattens the error side into a single `Error` carrying
  `status` and `errors`.
- Stores own success/error feedback for async actions. Components render, they
  do not decide what the user is told about a failed request.
- Styling is TailwindCSS 4, configured entirely through `@theme {}` in
  `resources/css/app.css`. There is no `tailwind.config.js`.

## Configuration

Two environment files, deliberately separate:

| File | Read by | Contains |
|---|---|---|
| `src/.env` | Laravel (PHP) | `APP_*`, `DB_*`, `REDIS_*`, API keys |
| `.env.docker` | Docker Compose | `APP_DOMAIN`, MySQL credentials, Grafana credentials |

They overlap on the database credentials on purpose: MySQL is *created* with the
values in `.env.docker` and *connected to* with the values in `src/.env`. If they
drift, the app cannot log in to its own database.

Neither file is in git. Every new variable must land in `src/.env.example` or
`.env.docker.example` in the same change that introduces it.

## Services

| Service | Dev | Prod |
|---|---|---|
| `app` | php-fpm 8.4, code bind-mounted | same image, opcache on, timestamps frozen |
| `nginx` | `:8080`, plain HTTP | `:80` → redirect, `:443` TLS from Let's Encrypt |
| `db` | MySQL 8.4 on `:3307`; also holds the `marketing_ai_testing` schema | MySQL 8.4, bound to `127.0.0.1:3306` |
| `redis` | `:6380` — cache, sessions, queue | internal only |
| `node` | Vite dev server on `:5173` | not present; assets are prebuilt by `deploy.sh` |
| `queue` | `queue:work` | `queue:work` with `--max-time=3600` |
| `scheduler` | not present | `schedule:work` |
| `mailpit` | `:8025` | not present |
| `loki`/`promtail`/`grafana` | `observability` profile | `observability` profile, Grafana on loopback only |

Production images are built from the same `Dockerfile`; only the mounted
`php.ini` and the environment differ.
