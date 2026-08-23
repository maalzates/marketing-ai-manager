# Backend guidelines

The code templates live in the `marketing-backend-ddd` skill
(`.claude/skills/marketing-backend-ddd/SKILL.md`). This document is the part the
templates cannot carry: **why** the architecture is shaped this way, and the
checklist for adding a module to it.

## Why a modular monolith

One deployable, many bounded slices. Each module owns its models, its rules, its
HTTP surface and its outbound calls, and exposes the rest of the app a Service —
nothing else.

The payoff is not "microservices later". It is that a change to how campaigns are
scored cannot reach into how YouTube data is fetched, because there is no path
between them that does not go through a Service. A flat `app/Services/` directory
gives you the same files with none of that guarantee.

The cost is ceremony: five files to add one endpoint. Accept it. The alternative
is a `CampaignController` that is 400 lines by month three.

## Why repositories return Collections

A repository that returns `array` has thrown away the query builder's type, the
model's accessors, and every Collection method the caller might want. It also
silently invites a hand-rolled `['data' => ..., 'meta' => ...]` envelope, which is
the resource layer's job.

Returning `Collection | Model | LengthAwarePaginator` keeps the decision about
shape at the edge, where the response is built.

## Why logging is not your job

`ApiException` knows its own severity (`getLogLevel()`) and carries structured
context. The handler in `bootstrap/app.php` reports it once, at the right level.

If a repository also calls `Log::error`, the same failure produces two entries at
two severities with two shapes, and every Loki query has to know both. Worse, the
manual call usually fires *before* the throw, so a failure that is later caught
and handled still shows up as an error in the dashboards.

So: attach context to the exception, throw, walk away.

**`ClientException` vs `ApiException`:** if the caller can fix it by sending a
different request, it is a `ClientException` and its message is rendered back to
them. If it is our fault, it is a plain `ApiException` — still rendered, but the
message must be one *you* wrote, never a provider's raw string. The provider's
detail belongs in `context`, which only reaches the logs.

## Why services never catch

An exception that reaches a Service has already been given context by the layer
that raised it. Catching it there means either re-throwing (noise) or swallowing
it (a bug that will be reported as "the button does nothing").

The only thing a Service does with failure is the null guard:

```php
return $this->repository->findById($id) ?? throw CampaignNotFoundException::withId($id);
```

## Why external calls go through Core

`GuzzleClientFactory` is the single place timeouts, headers and (later) retries
are set. A module that builds its own `new Client()` opts out of every future
change to that policy — a connect timeout raised across the app would silently
skip it.

`ApiClientAbstract` is the single place a non-2xx response becomes an exception
with the method, URI, options, status and decoded body in its context. That is
what makes a 4am "the Meta call failed" log line actionable.

A client's job is three things: name the endpoints as constants, shape the
parameters, and translate `ApiCallFailedException` into its own domain exception.
Nothing else. No caching, no business rules, no persistence.

## Adding a module — checklist

1. **Name it after the domain, not the technology.** `Campaigns`, `Competitors`,
   `Content` — not `Api`, `Jobs`, `Integrations`.
2. Create the directory skeleton (only the layers you actually need):
   ```
   app/Modules/{Module}/{Application/{DTO,Services},Domain/{Contracts,Enums,Exceptions},Infrastructure/{Persistence,Repositories,Clients},Presentation/Http/{Controllers/Api,Requests}}
   ```
3. Write the migration in `database/migrations/`, the model in
   `Infrastructure/Persistence/`, the factory in `database/factories/`.
4. Write the contract before the implementation. If the contract is awkward, the
   boundary is wrong.
5. Write `{Module}ServiceProvider.php`, bind every contract, register it in
   `bootstrap/providers.php`.
6. Add routes to `routes/api.php`, grouped and prefixed by module.
7. Add any new config to `config/services.php` and the variables to
   `src/.env.example`.
8. Tests: Service unit tests with the contract mocked, repository and endpoint
   feature tests against sqlite. See `.ai/test-guidelines.md`.

## Where things do NOT go

| Location | Why not |
|---|---|
| `app/Http/Controllers/` | Only Laravel's base `Controller` lives there. API controllers go in their module. |
| `app/Models/` | Only `User`, until the auth module claims it. New models go in `Infrastructure/Persistence/`. |
| `app/Services/` | Does not exist. Do not create it. |
| A helper file of loose functions | If it is shared, it is a Core class. If it is not, it belongs to one module. |

## Cross-module dependencies

A module may depend on another module's **Service**. It may never depend on
another module's repository, model or client.

When two modules need the same thing, the honest options are: move it to `Core`
(if it is infrastructure), or have one own it and expose a Service (if it is
domain). Reaching across into `App\Modules\Other\Infrastructure\...` is how a
modular monolith becomes a regular monolith with extra folders.

## Queues and scheduled work

Jobs live in the module that owns the work
(`app/Modules/{Module}/Application/Jobs/`). A job is a thin wrapper: resolve the
Service, call one method, let exceptions bubble so the queue retry logic and the
exception handler both see them. Business logic in a job body is business logic
that cannot be tested without the queue.

Anything with an LLM or external API in it goes on the queue, not in the request
cycle. A controller that waits 30 seconds for Claude is a controller that will
time out behind nginx.
