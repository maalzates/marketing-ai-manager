<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Presentation\Http\Controllers\Api;

use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use App\Modules\Integrations\Application\Services\IntegrationService;
use App\Modules\Integrations\Presentation\Http\Requests\ConnectIntegrationRequest;
use App\Modules\Integrations\Presentation\Http\Requests\IntegrationProviderRequest;
use App\Modules\Integrations\Presentation\Http\Resources\IntegrationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class IntegrationController extends ApiController
{
    public function __construct(
        private readonly IntegrationService $service,
        private readonly AccountContext $account,
    ) {
        parent::__construct();
    }

    public function index(): JsonResponse
    {
        return $this->response->success(
            IntegrationResource::collection($this->service->list($this->account->accountId))
        );
    }

    public function update(ConnectIntegrationRequest $request): JsonResponse
    {
        return $this->response->success(
            new IntegrationResource($this->service->connectApiKey($request->toDTO($this->account->accountId)))
        );
    }

    public function verify(IntegrationProviderRequest $request): JsonResponse
    {
        return $this->response->success(
            new IntegrationResource($this->service->verify($this->account->accountId, $request->provider()))
        );
    }

    public function destroy(IntegrationProviderRequest $request): Response
    {
        $this->service->disconnect($this->account->accountId, $request->provider());

        return $this->response->noContent();
    }
}
