<?php

declare(strict_types=1);

namespace App\Modules\Assets\Presentation\Http\Controllers\Api;

use App\Modules\Assets\Application\Services\AssetService;
use App\Modules\Assets\Presentation\Http\Requests\IndexAssetRequest;
use App\Modules\Assets\Presentation\Http\Requests\LinkExistingAssetRequest;
use App\Modules\Assets\Presentation\Http\Requests\LinkExperimentRequest;
use App\Modules\Assets\Presentation\Http\Requests\StoreAssetRequest;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AssetController extends ApiController
{
    public function __construct(
        private readonly AssetService $service,
        private readonly AccountContext $context,
    ) {
        parent::__construct();
    }

    public function index(IndexAssetRequest $request): JsonResponse
    {
        return $this->response->success($this->service->findAll($request->toDTO()));
    }

    public function store(StoreAssetRequest $request): JsonResponse
    {
        return $this->response->created($this->service->upload($request->toDTO()));
    }

    public function show(string $id): JsonResponse
    {
        return $this->response->success($this->service->find((int) $id, $this->context->accountId));
    }

    public function destroy(string $id): Response
    {
        $this->service->delete((int) $id, $this->context->accountId);

        return $this->response->noContent();
    }

    public function linkExisting(LinkExistingAssetRequest $request): JsonResponse
    {
        return $this->response->created($this->service->linkExisting($request->toDTO()));
    }

    public function linkExperiment(LinkExperimentRequest $request): JsonResponse
    {
        return $this->response->success($this->service->attachToExperiment($request->toDTO()));
    }

    public function ready(string $id): JsonResponse
    {
        return $this->response->success($this->service->markReady((int) $id, $this->context->accountId));
    }
}
