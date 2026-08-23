<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Controllers\Api;

use App\Modules\Admin\Application\Services\ApiKeyService;
use App\Modules\Admin\Infrastructure\Persistence\ApplicationApiKey;
use App\Modules\Admin\Presentation\Http\Requests\IndexApiKeyRequest;
use App\Modules\Admin\Presentation\Http\Requests\StoreApiKeyRequest;
use App\Modules\Admin\Presentation\Http\Resources\ApplicationApiKeyResource;
use App\Modules\Admin\Presentation\Http\Resources\IssuedApiKeyResource;
use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminApiKeyController extends ApiController
{
    public function __construct(private readonly ApiKeyService $service)
    {
        parent::__construct();
    }

    public function index(IndexApiKeyRequest $request): JsonResponse
    {
        // through() rather than a resource collection: it keeps the paginator envelope while
        // still passing every row through the field whitelist.
        return $this->response->success($this->service->findAll($request->toDTO())->through(
            fn (ApplicationApiKey $key): ApplicationApiKeyResource => new ApplicationApiKeyResource($key),
        ));
    }

    /** The only response carrying the token. Every later read of this key returns the prefix. */
    public function store(StoreApiKeyRequest $request): JsonResponse
    {
        return $this->response->created(new IssuedApiKeyResource($this->service->create($request->toDTO())));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        return $this->response->success(
            new ApplicationApiKeyResource($this->service->revoke((int) $id, (int) $request->user()->id))
        );
    }
}
