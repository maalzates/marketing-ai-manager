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
│   ├── routes/api.php       # every JSON endpoint, all under /api/v1
│   ├── routes/web.php       # /media/{token} + the catch-all that renders app.blade.php
│   ├── routes/console.php   # the scheduled jobs
│   └── tests/Feature/      # feature tests only; there is no tests/Unit
├── docker/                  # nginx, php.ini, mysql init, observability configs
├── scripts/                 # deploy.sh, setup-production.sh
├── docker-compose.yml       # development stack
├── docker-compose.prod.yml  # production stack
└── Makefile                 # every command a developer needs
```

## Request lifecycle

```
browser / Meta's fetcher
  → nginx (:80 dev, :80 prod behind Cloudflare)
      → /api/v1/*     → php-fpm → routes/api.php  → Controller → Service → Repository → Model
      → /api/health   → php-fpm → routes/api.php  → liveness probe, unversioned
      → /media/{token}→ php-fpm → routes/web.php  → signed stream from Drive
      → anything else → php-fpm → routes/web.php  → app.blade.php → Vue Router takes over
```

**Every JSON endpoint lives under `/api/v1`.** The prefix is applied once, in
`bootstrap/app.php`, not repeated per group.

Two routes sit outside that prefix, both deliberately:

- **`GET /api/health`** is unversioned. It is an infrastructure liveness probe, and a
  probe that moves when the API version changes is a probe that silently stops
  probing. `/up` is Laravel's own health endpoint and is what `deploy.sh`
  smoke-tests.
- **`GET /media/{token}`** lives in `routes/web.php`, outside `/api`, and is
  unauthenticated. Instagram's publishing API is *pull-based*: Meta downloads the
  piece from a publicly reachable URL and its fetcher carries no bearer token, so
  no amount of middleware would let it through. The token in the path is the whole
  authorisation — HMAC-SHA256 over `APP_KEY`, scoped to one asset and one account,
  24-hour expiry (the lifetime of an Instagram media container), with a nonce so
  every mint is a distinct URL. The response streams straight from Drive to the
  socket; nothing is written to this machine and no token is stored, which also
  means **nothing invalidates a token after its first use** — expiry and scope are
  the mitigation.

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

A module owns one bounded slice of the domain and **exposes the rest of the app
exactly one thing: a Service.** Not a repository, not a model, not a client, not a
job — a Service. If another module needs something, the answer is a method on a
Service or the boundary is wrong.

The twenty modules:

```
Accounts   Admin    Ai        Assets    Audit
Auth       Brands   Campaigns Chat      Competitors
Content    Core     Experiments         Integrations
Knowledge  Onboarding         Proposals Reporting
Settings   Strategies
```

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

`app/Modules/Core/` is the shared base every other module builds on and none of
them duplicates. It owns no domain of its own.

| Class | Role |
|---|---|
| `Application/Context/AccountContext` | The active account and user for this request or job. Injected into controllers, tools and FormRequests instead of each of them re-deriving it. |
| `Presentation/Http/Controllers/Api/ApiController` | Base controller; exposes `$this->response`. |
| `Presentation/Http/Middleware/EnsureAccountContext` | Resolves the authenticated user's active account and populates `AccountContext`. On every route that touches tenant data. |
| `Presentation/Http/Middleware/EnsureRole` | `EnsureRole:admin` on every `/api/v1/admin/*` route. Applied by class, not by alias. |
| `Presentation/Http/Responses/ApiResponse` | The single JSON envelope. |
| `Presentation/Http/Responses/ExceptionRenderer` | Maps any exception — domain, validation, auth, a router 404 — into that envelope. |
| `Presentation/Http/Requests/RequestHelperTrait` | Typed accessors for validated input. |
| `Presentation/Tools/ToolRegistry` | Maps tool name → Tool class, with its schema. A singleton; each module registers its own tools in its provider. The chat loop reads `definitions()` from it, and the future MCP adapter will read the same. |
| `Presentation/Tools/ToolAbstract` | Base chat tool: name, description, JSON schema, input validation against that schema, account context, `handle()`. |
| `Domain/Exceptions/ApiException` | Base domain exception: context, log level, HTTP status. |
| `Domain/Exceptions/ClientException` | An `ApiException` whose message is safe to show the caller. |
| `Domain/Exceptions/ApiCallFailedException` | Raised by `ApiClientAbstract` on any failed outbound call. |
| `Domain/Support/SecretMasker` | Replaces any array key containing `key`, `token`, `secret` or `password` with `****` plus the last four characters. Everything that reaches an action log or an exception context goes through it. |
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

#### Crossing a module boundary: the line is at use, not at import

A module may depend on another module's **Service**. It may never use another
module's repository or client.

Its model is the case that needs saying out loud, because the obvious phrasing
contradicts itself: repositories return `Model`, so a Service returns a `Model`
too, so **any caller of that Service is forced to name that type**. "Never depend
on another module's model" and "repositories return models" cannot both hold as
written.

The rule that actually matters is narrower:

> **Type-hinting another module's model is legitimate. Querying it is not.**

`Experiment::where(...)`, `new Experiment`, `$experiment->save()` from outside
`Experiments` skips the Service that holds that module's invariants — starting
with the `account_id` filter, and continuing with every rule the Service enforces
before a write. A type hint skips nothing; it is just the return type of a method
you were allowed to call.

So the line is drawn at **use**, not at import. `tests/Feature/Core/ModuleBoundariesTest.php`
enforces exactly that over the whole code tree, along with four other things: no
cross-module repository or client use, no cycles in the module dependency graph,
nothing under `Presentation/Tools/` reaching `ProposalExecutionService`, and
`strict_types` in every file. Two cycles appeared during the initial build
(Strategies↔Experiments and Brands↔Strategies) and both were invisible file by
file.
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

### Controller methods are named after the resource action

**Never `__invoke()`.** A controller action is always a method named for what it
does to the resource: `index`, `show`, `store`, `update`, `destroy` — the same
vocabulary Laravel's resource controllers use, extended only when the domain has
a verb of its own (`activate`, `pause`, `archive`, `approve`, `verify`).

```php
// ✅ the route names the action
Route::get('/usage', [UsageController::class, 'index']);
Route::post('/proposals/{id}/accept', [AcceptProposalController::class, 'store']);

// ❌ single-action controller
Route::get('/usage', UsageController::class);
```

A single-action controller is still fine as a *class* — `AcceptProposalController`
exists on its own precisely so the container can hand
`ProposalExecutionService` to it and to nothing else. What is not fine is naming
its method `__invoke`. Splitting a controller out is a decision about
dependencies; the method name is where a reader learns what the endpoint does,
and `__invoke` tells them nothing. `route:list` reads worse too, because every
row loses the verb.

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
| `nginx` | `:80` on the host (`HTTP_PORT` overrides), plain HTTP | `:80`, plain HTTP — TLS terminates at Cloudflare (Flexible); no `:443`, no certificate |
| `db` | MySQL 8.4 on `:3307`; also holds `marketing_ai_testing` and the parallel `marketing_ai_testing_{a,b,c,d,main}` schemas | MySQL 8.4, bound to `127.0.0.1:3306` |
| `redis` | `:6380` — cache, sessions, queue | internal only |
| `node` | Vite dev server on `:5173` | not present; assets are prebuilt by `deploy.sh` |
| `queue` | `queue:work` | `queue:work` with `--max-time=3600` |
| `scheduler` | not present | `schedule:work` |
| `loki`/`promtail`/`grafana` | `observability` profile | `observability` profile, Grafana on loopback only |

Production images are built from the same `Dockerfile`; only the mounted
`php.ini` and the environment differ.
