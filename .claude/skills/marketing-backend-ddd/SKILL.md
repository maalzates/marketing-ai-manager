---
name: marketing-backend-ddd
description: Backend architecture for Marketing AI Manager — Laravel modular monolith with Domain-Driven Design. Use for ANY backend work: new modules, controllers, services, repositories, DTOs, domain exceptions, external API clients, chat tools, jobs, service providers. CRITICAL RULES - every backend class lives in a module, ALL entry points (HTTP, chat tools, jobs, future MCP) converge on the same Services, mutation tools return Proposals and never execute, per-account credentials resolved at runtime (never env), repositories return Collections (NEVER arrays), never call Log:: by hand (throw domain exceptions), readonly classes, no single-use variables, declare(strict_types=1) everywhere.
user-invocable: true
disable-model-invocation: false
argument-hint: [module|controller|service|repo|dto|exception|client|tool|job]
---

# Backend DDD — Marketing AI Manager

Every line of backend code in this project follows this architecture. There is no
"outside the modules" — if it is business logic, it lives in
`app/Modules/{Module}/`.

## Architectural flow — one core, many doors

The Service layer is the single convergence point. Every way of triggering the
application — an HTTP request, the in-app chat assistant, a scheduled job, and
(future) an MCP server — is a **driving adapter**: a thin door that translates
its own input format into a DTO and calls the same Service.

```
HTTP request        Chat tool_use         Scheduled job        MCP call (future)
     ↓                    ↓                     ↓                    ↓
FormRequest          Tool class            Job class            Mcp handler
validate + toDTO()   validate + DTO        build DTO            reuses Tool defs
     ↓                    ↓                     ↓                    ↓
Controller ─────────────→ Service ←────────────┴────────────────────┘
                             ↓
                        Repository / Client      the only layers touching
                             ↓                   Eloquent or outbound HTTP
                        Model / ApiClient
```

Rules that make this safe:

1. **Doors never contain business logic.** A Controller, Tool, Job or Mcp
   handler validates its input, builds a DTO and delegates. If you are writing
   an `if` about budgets, verdicts or learning phases inside a door, stop — it
   belongs in a Service or the Domain layer.
2. **Invariants live in Services/Domain**, so no door can bypass them. Budget
   caps, approval requirements and guardrails are enforced once, in the center,
   and hold for every door automatically.
3. **Validation is per-door at the boundary, invariants are central.**
   FormRequests validate HTTP input; Tools validate LLM-provided input against
   the tool schema; Jobs trust their own construction. Domain invariants
   (budget ≤ cap, experiment has expected result, proposal approved) are NOT
   validation — they are Service/Domain logic and never live in a door.

DTOs flow **one way**: Door → Service → Repository. Nothing flows back as a
DTO; repositories return Models, Collections or paginators.

## Module structure

```
app/Modules/{Module}/
├── Application/
│   ├── DTO/                     # readonly data carriers
│   └── Services/                # readonly, business logic
├── Domain/
│   ├── Contracts/               # repository + client interfaces
│   ├── Enums/
│   └── Exceptions/              # extend Core's ApiException / ClientException
├── Infrastructure/
│   ├── Persistence/             # Eloquent models
│   ├── Repositories/            # readonly, implements the contracts
│   └── Clients/                 # external API clients, extend ApiClientAbstract
├── Presentation/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   └── Requests/
│   ├── Tools/                   # chat assistant tools (driving adapters)
│   └── Jobs/                    # queued/scheduled entry points (guardián, sync)
└── {Module}ServiceProvider.php  # binds contracts → implementations, registers tools
```

Register the provider in `bootstrap/providers.php`. Migrations stay in
`database/migrations/` (Laravel's loader is global); factories in
`database/factories/`.

## The Core module — what you inherit, never re-invent

`app/Modules/Core/` holds what every other module builds on. Never duplicate any
of it inside a feature module.

| Class | What it gives you |
|---|---|
| `Presentation/Http/Controllers/Api/ApiController` | Base controller. Injects `ApiResponse` as `$this->response`. |
| `Presentation/Http/Responses/ApiResponse` | The one JSON envelope: `success()`, `created()`, `accepted()`, `error()`, `noContent()`. |
| `Presentation/Http/Responses/ExceptionRenderer` | Maps any exception into that envelope; wired in `bootstrap/app.php`. |
| `Presentation/Http/Requests/RequestHelperTrait` | Typed accessors (`getStringValue`, `getEnumValue`, `mergeStringified`, …) so `toDTO()` stays cast-free. |
| `Presentation/Tools/ToolRegistry` | Maps tool name → Tool class; exposes name/description/schema per tool. The chat loop and the future MCP adapter both read from it. |
| `Presentation/Tools/ToolAbstract` | Base chat tool: schema definition, input validation against the schema, account context injection. |
| `Domain/Exceptions/ApiException` | Base domain exception. Carries `context`, decides its own log level, clamps its HTTP status. |
| `Domain/Exceptions/ClientException` | An `ApiException` whose message is safe to show the end user, plus `extras`. |
| `Domain/Exceptions/ApiCallFailedException` | What `ApiClientAbstract` throws when an outbound call fails. |
| `Infrastructure/Clients/GuzzleClientFactory` | Builds every outbound Guzzle client with shared timeouts and headers. |
| `Infrastructure/Clients/ApiClientAbstract` | `get/post/put/patch/delete`, JSON decoding, error translation. |

### The response envelope

Every JSON response, success or failure:

```json
{ "result": { "id": "camp_1" }, "errors": [] }
{ "result": [], "errors": { "message": "Campaign not found", "status_code": 404 } }
```

The error side is produced by `ExceptionRenderer` — for domain exceptions and
for everything Laravel raises (validation, auth, a router 404). A validation
failure adds `errors.fields`. A controller never builds an error response by
hand.

## CRITICAL standards (non-negotiable)

### 1. Repositories return Collections, never arrays

```php
// ✅
public function findAll(CampaignFilterDTO $filters): Collection|LengthAwarePaginator
{
    return $filters->perPage > 0
        ? $this->model->newQuery()->paginate($filters->perPage)
        : $this->model->newQuery()->get();
}

// ❌
public function findAll(CampaignFilterDTO $filters): array
{
    return $this->model->newQuery()->get()->toArray();
}

// ❌ worse — a hand-rolled envelope
return ['data' => [...], 'meta' => [...]];
```

Returning a Collection keeps `map`/`filter`/`groupBy` available all the way up
and lets the resource layer decide the shape.

### 2. Never call `Log::` by hand — throw a domain exception

The global handler in `bootstrap/app.php` reports every `ApiException` with its
own severity and context. That is the *only* place logging happens.

```php
// ✅ the repository attaches context to the exception and throws
public function create(CreateCampaignDTO $dto): Campaign
{
    try {
        return $this->model->create([
            'name' => $dto->name,
            'objective' => $dto->objective,
        ]);
    } catch (Throwable $exception) {
        throw CampaignCreationFailedException::wrap(
            $exception,
            context: ['name' => $dto->name],
        );
    }
}

// ❌ never
Log::error('Failed to create campaign', [...]);
```

Why: a manual `Log::error` plus a thrown exception logs the same failure twice,
at two severities, with two different shapes — and Loki queries then have to know
both. One exception, one log line.

**The action log and consumption log are NOT `Log::`.** They are domain data
(audit tables written through their own module's Service/Repository, typically
via events). "User accepted proposal X" goes to the action log module;
"call to Claude used N tokens" goes to the consumption module. Neither ever
touches the application log.

### 3. Exception handling ONLY in repositories and clients

Services, controllers, tools and jobs never `try`/`catch`. Let it bubble.
(The chat loop in Core catches `ClientException` to surface a safe message to
the model as a `tool_result` error — that is Core's job, not the Tool's.)

```php
// ✅ service — no try/catch, just the null guard
public function findById(string $id): Campaign
{
    return $this->repository->findById($id) ?? throw CampaignNotFoundException::withId($id);
}
```

### 4. `readonly` classes for DTOs, Services, Repositories and Tools

```php
readonly class CampaignService
{
    public function __construct(private CampaignRepositoryInterface $repository) {}
}
```

### 5. No single-use variables

```php
// ✅
public function store(CreateCampaignRequest $request): JsonResponse
{
    return $this->response->created($this->service->create($request->toDTO()));
}

// ❌
$dto = $request->toDTO();
$campaign = $this->service->create($dto);
return $this->response->created($campaign);
```

Exception: a value referenced more than once, or a name that genuinely explains
an opaque expression.

### 6. Transformation in `prepareForValidation()`, mapping in `toDTO()`

```php
// ✅
protected function prepareForValidation(): void
{
    $this->merge([
        'slug' => str($this->input('name'))->slug()->value(),
        'status' => $this->input('status', 'draft'),
    ]);
}

public function toDTO(): CreateCampaignDTO
{
    return new CreateCampaignDTO(
        $this->input('name'),
        $this->input('slug'),
        $this->input('status'),
    );
}

// ❌ logic inside toDTO()
return new CreateCampaignDTO($this->input('name'), str($this->input('name'))->slug());
```

### 7. Named parameters only when skipping optional ones

```php
// ✅ all in order, no names
return new CreateCampaignDTO($this->input('name'), $this->input('objective'));

// ✅ named because the source differs / an optional is skipped
return new UpdateCampaignDTO(
    campaignId: $this->route('campaign'),
    $this->input('name'),
);

// ❌ names for every argument passed in order
return new CreateCampaignDTO(name: $this->input('name'), objective: $this->input('objective'));
```

### 8. `declare(strict_types=1)` in every PHP file

### 9. Doors depend on Services; Services depend on contracts

A Controller/Tool/Job type-hints the Service; a Service type-hints the
repository **interface**. Nothing type-hints a concrete repository.

### 10. Every tenant-owned query is account-scoped

Every table that belongs to a user carries `account_id`. Repositories receive
the account context through the DTO and always filter by it. No Service, Tool
or Job may reach data across accounts. Tests must assert the isolation.

```php
// ✅ every filter DTO carries the account
private function query(CampaignFilterDTO $filters): Builder
{
    return $this->model->newQuery()
        ->where('account_id', $filters->accountId)
        ->when($filters->status, fn (Builder $query, string $status) => $query->where('status', $status));
}
```

### 11. Mutation tools return Proposals — they never execute

The chat assistant (and the guardián) can only *propose* destructive actions:
creating campaigns, changing budgets, pausing. A mutation Tool calls a
`Propose*Service` that persists a `Proposal` and returns it. The executing
Service is reachable **only** from the human approval door
(`POST /api/v1/proposals/{id}/accept`), which the LLM does not know about.
There is no code path from a Tool to an executing Service. This is the
architecture enforcing the approval invariant — never the prompt.

### 12. Per-account credentials (BYOK) are resolved at runtime, never from env

Two kinds of credentials, two mechanisms:

- **Platform credentials** (one per deployment: Google OAuth client id/secret,
  Meta App id/secret, DB, Redis): `config/services.php` reading `env()`. These
  identify *the application*.
- **Account credentials** (per user: Apify key, LLM key, Meta/Google OAuth
  tokens): stored encrypted in the account's integrations, resolved **per
  request/job** through a client factory. These identify *the user*.

```php
// ✅ factory builds a per-account client from decrypted integration credentials
readonly class ApifyClientFactory
{
    public function __construct(
        private GuzzleClientFactory $guzzle,
        private IntegrationRepositoryInterface $integrations,
    ) {}

    public function forAccount(string $accountId): ApifyClient
    {
        return new ApifyClient($this->guzzle->create([
            'base_uri' => config('services.apify.base_url'),
            'headers' => ['Authorization' => 'Bearer ' . $this->integrations->apifyKey($accountId)],
        ]));
    }
}

// ❌ never — a singleton client with a global key serves every account with YOUR key
$this->app->singleton(ApifyClient::class, fn () => new ApifyClient(/* env key */));
```

Never cache an account-scoped client (or its key) in a singleton, static
property or shared container state. Resolve per account, use, discard.

## Templates

### FormRequest

```php
<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Presentation\Http\Requests;

use App\Modules\Campaigns\Application\DTO\CreateCampaignDTO;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;

class CreateCampaignRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'objective' => ['required', 'string', 'max:1000'],
            'monthly_budget' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['status' => $this->input('status', 'draft')]);
    }

    public function toDTO(): CreateCampaignDTO
    {
        return new CreateCampaignDTO(
            $this->user()->currentAccountId(),
            $this->getStringValue('name'),
            $this->getStringValue('objective'),
            $this->getFloatValue('monthly_budget'),
            $this->getStringValue('status'),
        );
    }
}
```

### Controller

```php
<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Presentation\Http\Controllers\Api;

use App\Modules\Campaigns\Application\Services\CampaignService;
use App\Modules\Campaigns\Presentation\Http\Requests\CreateCampaignRequest;
use App\Modules\Campaigns\Presentation\Http\Requests\IndexCampaignRequest;
use App\Modules\Campaigns\Presentation\Http\Requests\UpdateCampaignRequest;
use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CampaignController extends ApiController
{
    public function __construct(private readonly CampaignService $service)
    {
        parent::__construct();
    }

    public function index(IndexCampaignRequest $request): JsonResponse
    {
        return $this->response->success($this->service->findAll($request->toDTO()));
    }

    public function show(string $campaign): JsonResponse
    {
        return $this->response->success($this->service->findById($campaign));
    }

    public function store(CreateCampaignRequest $request): JsonResponse
    {
        return $this->response->created($this->service->create($request->toDTO()));
    }

    public function update(UpdateCampaignRequest $request): JsonResponse
    {
        return $this->response->success($this->service->update($request->toDTO()));
    }

    public function destroy(string $campaign): Response
    {
        $this->service->delete($campaign);

        return $this->response->noContent();
    }
}
```

### Chat tool — read (executes directly)

```php
<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Presentation\Tools;

use App\Modules\Core\Presentation\Tools\ToolAbstract;
use App\Modules\Experiments\Application\DTO\ExperimentFilterDTO;
use App\Modules\Experiments\Application\Services\ExperimentService;
use Illuminate\Support\Collection;

readonly class GetExperimentsTool extends ToolAbstract
{
    public function __construct(private ExperimentService $service)
    {
    }

    public static function name(): string
    {
        return 'get_experiments';
    }

    public static function description(): string
    {
        return 'List experiments of a strategy, optionally filtered by verdict.';
    }

    public static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'strategy_id' => ['type' => 'string'],
                'verdict' => ['type' => 'string', 'enum' => ['worked', 'failed', 'inconclusive']],
            ],
            'required' => ['strategy_id'],
        ];
    }

    /** $input is already validated against schema() by ToolAbstract */
    public function handle(string $accountId, array $input): Collection
    {
        return $this->service->findAll(new ExperimentFilterDTO(
            $accountId,
            $input['strategy_id'],
            $input['verdict'] ?? null,
        ));
    }
}
```

### Chat tool — mutation (creates a Proposal, never executes)

```php
<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Presentation\Tools;

use App\Modules\Core\Presentation\Tools\ToolAbstract;
use App\Modules\Proposals\Application\DTO\CreateProposalDTO;
use App\Modules\Proposals\Application\Services\ProposalService;
use App\Modules\Proposals\Domain\Enums\ProposalType;
use App\Modules\Proposals\Infrastructure\Persistence\Proposal;

readonly class ProposeCampaignPauseTool extends ToolAbstract
{
    /** Depends on ProposalService — the executing CampaignPauseService is NOT importable here */
    public function __construct(private ProposalService $proposals)
    {
    }

    public static function name(): string
    {
        return 'propose_campaign_pause';
    }

    public static function description(): string
    {
        return 'Propose pausing a campaign. Requires human approval before anything happens.';
    }

    public static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'campaign_id' => ['type' => 'string'],
                'reason' => ['type' => 'string'],
            ],
            'required' => ['campaign_id', 'reason'],
        ];
    }

    public function handle(string $accountId, array $input): Proposal
    {
        return $this->proposals->create(new CreateProposalDTO(
            $accountId,
            ProposalType::PauseCampaign,
            $input['campaign_id'],
            $input['reason'],
        ));
    }
}
```

The `ProposalService::create()` runs the domain checks that apply at proposal
time (campaign exists, belongs to the account, learning-phase warning attached).
Execution checks (budget still valid, campaign still active) run again inside
the accepting Service when the human approves.

### Job (guardián entry point)

```php
<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Presentation\Jobs;

use App\Modules\Reporting\Application\DTO\GuardianRunDTO;
use App\Modules\Reporting\Application\Services\GuardianService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunGuardianForStrategyJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $accountId, private readonly string $strategyId)
    {
    }

    public function handle(GuardianService $service): void
    {
        $service->run(new GuardianRunDTO($this->accountId, $this->strategyId));
    }
}
```

`GuardianService` holds all logic: auto-skip when no active experiments,
anomaly thresholds, learning-phase respect, and — when it finds an anomaly —
creating a `Proposal` through the same `ProposalService` the chat tools use.
The Job is a door; it decides nothing.

### DTO

```php
<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\DTO;

readonly class CreateCampaignDTO
{
    public function __construct(
        public string $accountId,
        public string $name,
        public string $objective,
        public ?float $monthlyBudget,
        public ?string $status,
    ) {}
}
```

### Service

```php
<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Services;

use App\Modules\Campaigns\Application\DTO\CampaignFilterDTO;
use App\Modules\Campaigns\Application\DTO\CreateCampaignDTO;
use App\Modules\Campaigns\Application\DTO\UpdateCampaignDTO;
use App\Modules\Campaigns\Domain\Contracts\CampaignRepositoryInterface;
use App\Modules\Campaigns\Domain\Exceptions\CampaignBudgetExceededException;
use App\Modules\Campaigns\Domain\Exceptions\CampaignNotFoundException;
use App\Modules\Campaigns\Infrastructure\Persistence\Campaign;
use App\Modules\Strategies\Domain\Contracts\StrategyBudgetGuardInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

readonly class CampaignService
{
    public function __construct(
        private CampaignRepositoryInterface $repository,
        private StrategyBudgetGuardInterface $budgetGuard,
    ) {}

    public function findAll(CampaignFilterDTO $filters): Collection|LengthAwarePaginator
    {
        return $this->repository->findAll($filters);
    }

    public function findById(string $accountId, string $id): Campaign
    {
        return $this->repository->findById($accountId, $id) ?? throw CampaignNotFoundException::withId($id);
    }

    public function create(CreateCampaignDTO $dto): Campaign
    {
        $this->budgetGuard->assertWithinBudget($dto->accountId, $dto->monthlyBudget)
            ?: throw CampaignBudgetExceededException::forBudget($dto->monthlyBudget);

        return $this->repository->create($dto);
    }

    public function update(UpdateCampaignDTO $dto): Campaign
    {
        return $this->repository->update(
            $this->findById($dto->accountId, $dto->campaignId),
            $dto,
        );
    }

    public function delete(string $accountId, string $id): bool
    {
        return $this->repository->delete($this->findById($accountId, $id));
    }
}
```

Note `create()`: the budget invariant lives here, in the center. It holds
whether the call came from the UI, the chat, the guardián or a future MCP
client — no door can skip it.

### Repository contract

```php
<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Domain\Contracts;

use App\Modules\Campaigns\Application\DTO\CampaignFilterDTO;
use App\Modules\Campaigns\Application\DTO\CreateCampaignDTO;
use App\Modules\Campaigns\Application\DTO\UpdateCampaignDTO;
use App\Modules\Campaigns\Infrastructure\Persistence\Campaign;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CampaignRepositoryInterface
{
    public function findAll(CampaignFilterDTO $filters): Collection|LengthAwarePaginator;

    public function findById(string $accountId, string $id): ?Campaign;

    public function create(CreateCampaignDTO $dto): Campaign;

    public function update(Campaign $campaign, UpdateCampaignDTO $dto): Campaign;

    public function delete(Campaign $campaign): bool;
}
```

### Repository implementation

```php
<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Infrastructure\Repositories;

use App\Modules\Campaigns\Application\DTO\CampaignFilterDTO;
use App\Modules\Campaigns\Application\DTO\CreateCampaignDTO;
use App\Modules\Campaigns\Application\DTO\UpdateCampaignDTO;
use App\Modules\Campaigns\Domain\Contracts\CampaignRepositoryInterface;
use App\Modules\Campaigns\Domain\Exceptions\CampaignCreationFailedException;
use App\Modules\Campaigns\Domain\Exceptions\CampaignUpdateFailedException;
use App\Modules\Campaigns\Infrastructure\Persistence\Campaign;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

readonly class CampaignRepository implements CampaignRepositoryInterface
{
    public function __construct(private Campaign $model) {}

    public function findAll(CampaignFilterDTO $filters): Collection|LengthAwarePaginator
    {
        return $filters->perPage > 0
            ? $this->query($filters)->paginate(perPage: $filters->perPage, page: $filters->page)
            : $this->query($filters)->get();
    }

    public function findById(string $accountId, string $id): ?Campaign
    {
        return $this->model->newQuery()->where('account_id', $accountId)->find($id);
    }

    public function create(CreateCampaignDTO $dto): Campaign
    {
        try {
            return $this->model->newQuery()->create([
                'account_id' => $dto->accountId,
                'name' => $dto->name,
                'objective' => $dto->objective,
                'monthly_budget' => $dto->monthlyBudget,
                'status' => $dto->status,
            ]);
        } catch (Throwable $exception) {
            throw CampaignCreationFailedException::wrap($exception, context: ['name' => $dto->name]);
        }
    }

    public function update(Campaign $campaign, UpdateCampaignDTO $dto): Campaign
    {
        try {
            $campaign->update(array_filter([
                'name' => $dto->name,
                'objective' => $dto->objective,
                'monthly_budget' => $dto->monthlyBudget,
                'status' => $dto->status,
            ], fn (mixed $value): bool => $value !== null));

            return $campaign->refresh();
        } catch (Throwable $exception) {
            throw CampaignUpdateFailedException::wrap($exception, context: ['campaign_id' => $campaign->id]);
        }
    }

    public function delete(Campaign $campaign): bool
    {
        return (bool) $campaign->delete();
    }

    private function query(CampaignFilterDTO $filters): Builder
    {
        return $this->model->newQuery()
            ->where('account_id', $filters->accountId)
            ->when($filters->search, fn (Builder $query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->when($filters->status, fn (Builder $query, string $status) => $query->where('status', $status));
    }
}
```

A read that finds nothing returns `null` or an empty Collection — that is not an
error and must not be caught or logged. Only writes and genuinely exceptional
reads get a `try`/`catch`.

### Domain exceptions

```php
<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class CampaignNotFoundException extends ClientException
{
    public static function withId(string $id): self
    {
        $exception = new self('Campaign not found.', Response::HTTP_NOT_FOUND);
        $exception->context = ['campaign_id' => $id];

        return $exception;
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ApiException;

class CampaignCreationFailedException extends ApiException
{
    // Inherits wrap(): CampaignCreationFailedException::wrap($throwable, context: [...])
}
```

Rule of thumb: **`ClientException` when the caller did something wrong and should
read the message; `ApiException` when we did.**

### Service provider

```php
<?php

declare(strict_types=1);

namespace App\Modules\Campaigns;

use App\Modules\Campaigns\Domain\Contracts\CampaignRepositoryInterface;
use App\Modules\Campaigns\Infrastructure\Repositories\CampaignRepository;
use App\Modules\Campaigns\Presentation\Tools\ProposeCampaignPauseTool;
use App\Modules\Core\Presentation\Tools\ToolRegistry;
use Illuminate\Support\ServiceProvider;

class CampaignsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CampaignRepositoryInterface::class, CampaignRepository::class);
    }

    public function boot(ToolRegistry $tools): void
    {
        $tools->register(ProposeCampaignPauseTool::class);
    }
}
```

Then add it to `bootstrap/providers.php`.

## External API clients

Every outbound integration — Meta Marketing API, Apify, YouTube Data API, the
LLM — follows the same pieces. **First decide which credential kind it uses**
(standard §12): platform credentials → provider binding with `config()`;
account credentials → a factory resolving from the account's integrations.

### 1. The client

```php
<?php

declare(strict_types=1);

namespace App\Modules\Youtube\Infrastructure\Clients;

use App\Modules\Core\Domain\Exceptions\ApiCallFailedException;
use App\Modules\Core\Infrastructure\Clients\ApiClientAbstract;
use App\Modules\Youtube\Domain\Exceptions\YoutubeClientException;

class YoutubeClient extends ApiClientAbstract
{
    private const string CHANNELS_ENDPOINT = '/youtube/v3/channels';

    private const string SEARCH_ENDPOINT = '/youtube/v3/search';

    /**
     * @throws YoutubeClientException
     */
    public function getChannel(string $channelId): array
    {
        try {
            return $this->get(self::CHANNELS_ENDPOINT, [
                'part' => 'snippet,statistics',
                'id' => $channelId,
            ]);
        } catch (ApiCallFailedException $exception) {
            throw YoutubeClientException::fromApiCallFailedException($exception);
        }
    }

    /**
     * @throws YoutubeClientException
     */
    public function searchVideos(string $channelId, int $maxResults = 25): array
    {
        try {
            return $this->get(self::SEARCH_ENDPOINT, [
                'part' => 'snippet',
                'channelId' => $channelId,
                'order' => 'date',
                'maxResults' => $maxResults,
            ]);
        } catch (ApiCallFailedException $exception) {
            throw YoutubeClientException::fromApiCallFailedException($exception);
        }
    }
}
```

Endpoints are class constants. The client returns decoded arrays and translates
`ApiCallFailedException` into its own domain exception — it never lets a Guzzle
type escape the Infrastructure layer.

### 2a. Binding — platform-credential client

```php
public function register(): void
{
    $this->app->singleton(YoutubeClient::class, fn (Application $app) => new YoutubeClient(
        $app->make(GuzzleClientFactory::class)->create([
            'base_uri' => config('services.youtube.base_url'),
            'query' => ['key' => config('services.youtube.api_key')],
        ])
    ));
}
```

### 2b. Binding — account-credential client (BYOK)

Bind the **factory** as singleton, never the client. Consumers depend on the
factory and call `forAccount()` per operation (see standard §12 for the full
factory template).

```php
public function register(): void
{
    $this->app->singleton(ApifyClientFactory::class);
}
```

### 3. The config entry (platform values only)

```php
// config/services.php
'youtube' => [
    'base_url' => env('YOUTUBE_BASE_URL', 'https://www.googleapis.com'),
    'api_key' => env('YOUTUBE_API_KEY'),
],
```

Credentials come from `config/services.php`, which reads `env()`. **Never call
`env()` outside `config/`** — production runs on a cached config and `env()`
returns `null` there. Account keys never appear in `config/` or `.env` at all.

Add the platform variables to `src/.env.example` in the same change.

### Testing a client

Inject a Guzzle `MockHandler`; never hit the network. See
`tests/Unit/Core/ApiClientAbstractTest.php` for the pattern.

```php
new YoutubeClient(new Client(['handler' => HandlerStack::create(new MockHandler([
    new Response(200, [], '{"items":[]}'),
]))]));
```

## DO ✅

1. Put every backend class in a module
2. Route every door (HTTP, tool, job, MCP) through the same Services
3. Return Collections / Models / paginators from repositories
4. Throw domain exceptions with context; let the handler log
5. `try`/`catch` only in repositories and clients
6. `readonly` for DTOs, Services, Repositories, Tools
7. Inline single-use values
8. Transform in `prepareForValidation()`
9. Bind contracts (and register tools) in the module's service provider
10. `declare(strict_types=1)` everywhere
11. `?? throw` for null guards
12. Endpoints as class constants in clients
13. `config()` in code, `env()` only in `config/`
14. Scope every tenant query by `account_id` and test the isolation
15. Resolve account credentials per operation through a client factory
16. Make mutation tools produce Proposals through `ProposalService`

## DON'T ❌

1. Never put business logic in `app/Http/`, `app/Models/` or any door (controller, tool, job)
2. Never return arrays from repositories
3. Never call `Log::` by hand (audit/consumption logs are domain data, not `Log::`)
4. Never `catch` in a Service, Controller, Tool or Job
5. Never put logic in `toDTO()`
6. Never type-hint a concrete repository
7. Never let Guzzle types escape `Infrastructure/Clients/`
8. Never add PHPDoc that restates the signature
9. Never use named parameters for every argument passed in order
10. Never build an error response by hand in a controller
11. Never call `env()` outside `config/`
12. Never hit a real API in a test
13. Never give a mutation Tool a path to an executing Service — Proposals only
14. Never store or read account keys from env/config, and never cache them in singletons or shared state
15. Never enforce an invariant (budget, approval, learning phase) in a door or a prompt — Services/Domain only

## Reference

- `guidelines/backend_guidelines.md` — the long-form version with rationale
- `docs/ARCHITECTURE.md` — layering, request lifecycle, services
- `.ai/test-guidelines.md` — how the tests for all of this are written
- `app/Modules/Core/` — read it before writing anything; it is the only module
  that already exists
