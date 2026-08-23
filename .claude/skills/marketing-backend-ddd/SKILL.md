---
name: marketing-backend-ddd
description: Backend architecture for Marketing AI Manager — Laravel modular monolith with Domain-Driven Design. Use for ANY backend work: new modules, controllers, services, repositories, DTOs, domain exceptions, external API clients, service providers. CRITICAL RULES - every backend class lives in a module, repositories return Collections (NEVER arrays), never call Log:: by hand (throw domain exceptions), readonly classes, no single-use variables, declare(strict_types=1) everywhere.
user-invocable: true
disable-model-invocation: false
argument-hint: [module|controller|service|repo|dto|exception|client]
---

# Backend DDD — Marketing AI Manager

Every line of backend code in this project follows this architecture. There is no
"outside the modules" — if it is business logic, it lives in
`app/Modules/{Module}/`.

## Architectural flow

```
HTTP request
    ↓
FormRequest          validation + prepareForValidation() + toDTO()
    ↓
Controller           HTTP only; extends Core's ApiController
    ↓
Service              business logic; depends on repository CONTRACTS
    ↓
Repository           the only place that touches Eloquent or an API client
    ↓
Model / ApiClient    persistence / outbound HTTP
```

DTOs flow **one way**: Controller → Service → Repository. Nothing flows back as a
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
│   └── Http/
│       ├── Controllers/Api/
│       └── Requests/
└── {Module}ServiceProvider.php  # binds contracts → implementations
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

### 3. Exception handling ONLY in repositories and clients

Services and controllers never `try`/`catch`. Let it bubble.

```php
// ✅ service — no try/catch, just the null guard
public function findById(string $id): Campaign
{
    return $this->repository->findById($id) ?? throw CampaignNotFoundException::withId($id);
}
```

### 4. `readonly` classes for DTOs, Services and Repositories

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

### 9. Controllers depend on contracts through the provider

A controller type-hints the Service; a Service type-hints the repository
**interface**. Nothing type-hints a concrete repository.

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

### DTO

```php
<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\DTO;

readonly class CreateCampaignDTO
{
    public function __construct(
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
use App\Modules\Campaigns\Domain\Exceptions\CampaignNotFoundException;
use App\Modules\Campaigns\Infrastructure\Persistence\Campaign;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

readonly class CampaignService
{
    public function __construct(private CampaignRepositoryInterface $repository) {}

    public function findAll(CampaignFilterDTO $filters): Collection|LengthAwarePaginator
    {
        return $this->repository->findAll($filters);
    }

    public function findById(string $id): Campaign
    {
        return $this->repository->findById($id) ?? throw CampaignNotFoundException::withId($id);
    }

    public function create(CreateCampaignDTO $dto): Campaign
    {
        return $this->repository->create($dto);
    }

    public function update(UpdateCampaignDTO $dto): Campaign
    {
        return $this->repository->update(
            $this->findById($dto->campaignId),
            $dto,
        );
    }

    public function delete(string $id): bool
    {
        return $this->repository->delete($this->findById($id));
    }
}
```

Note `update()` and `delete()`: the existence check reuses `findById()`, which
already throws, and hands the loaded model to the repository — one query, no
duplicated guard, no unreachable code.

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

    public function findById(string $id): ?Campaign;

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

    public function findById(string $id): ?Campaign
    {
        return $this->model->newQuery()->find($id);
    }

    public function create(CreateCampaignDTO $dto): Campaign
    {
        try {
            return $this->model->newQuery()->create([
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
use Illuminate\Support\ServiceProvider;

class CampaignsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CampaignRepositoryInterface::class, CampaignRepository::class);
    }
}
```

Then add it to `bootstrap/providers.php`.

## External API clients

Every outbound integration — Meta Marketing API, YouTube Data API, Anthropic —
follows the same three pieces.

### 1. The client

```php
<?php

declare(strict_types=1);

namespace App\Modules\Youtube\Infrastructure\Clients;

use App\Modules\Core\Domain\Exceptions\ApiCallFailedException;
use App\Modules\Core\Infrastructure\Clients\ApiClientAbstract;
use App\Modules\Youtube\Domain\Exceptions\YoutubeClientException;
use GuzzleHttp\RequestOptions;

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

### 2. The binding

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

Credentials come from `config/services.php`, which reads `env()`. **Never call
`env()` outside `config/`** — production runs on a cached config and `env()`
returns `null` there.

### 3. The config entry

```php
// config/services.php
'youtube' => [
    'base_url' => env('YOUTUBE_BASE_URL', 'https://www.googleapis.com'),
    'api_key' => env('YOUTUBE_API_KEY'),
],
```

Add the variables to `src/.env.example` in the same change.

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
2. Return Collections / Models / paginators from repositories
3. Throw domain exceptions with context; let the handler log
4. `try`/`catch` only in repositories and clients
5. `readonly` for DTOs, Services, Repositories
6. Inline single-use values
7. Transform in `prepareForValidation()`
8. Bind contracts in the module's service provider
9. `declare(strict_types=1)` everywhere
10. `?? throw` for null guards
11. Endpoints as class constants in clients
12. `config()` in code, `env()` only in `config/`

## DON'T ❌

1. Never put business logic in `app/Http/` or `app/Models/`
2. Never return arrays from repositories
3. Never call `Log::` by hand
4. Never `catch` in a Service or Controller
5. Never put logic in `toDTO()`
6. Never type-hint a concrete repository
7. Never let Guzzle types escape `Infrastructure/Clients/`
8. Never add PHPDoc that restates the signature
9. Never use named parameters for every argument passed in order
10. Never build an error response by hand in a controller
11. Never call `env()` outside `config/`
12. Never hit a real API in a test

## Reference

- `guidelines/backend_guidelines.md` — the long-form version with rationale
- `docs/ARCHITECTURE.md` — layering, request lifecycle, services
- `.ai/test-guidelines.md` — how the tests for all of this are written
- `app/Modules/Core/` — read it before writing anything; it is the only module
  that already exists
