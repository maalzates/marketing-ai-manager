<?php

declare(strict_types=1);

namespace App\Modules\Brands\Presentation\Http\Controllers\Api;

use App\Modules\Brands\Application\Services\BrandProfileService;
use App\Modules\Brands\Presentation\Http\Requests\StoreBrandProfileRequest;
use App\Modules\Brands\Presentation\Http\Requests\UpdateBrandProfileRequest;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class BrandProfileController extends ApiController
{
    public function __construct(
        private readonly BrandProfileService $service,
        private readonly AccountContext $context,
    ) {
        parent::__construct();
    }

    public function index(): JsonResponse
    {
        return $this->response->success($this->service->forAccount($this->context->accountId));
    }

    public function show(string $id): JsonResponse
    {
        return $this->response->success($this->service->find((int) $id, $this->context->accountId));
    }

    public function store(StoreBrandProfileRequest $request): JsonResponse
    {
        return $this->response->created($this->service->create($request->toDTO()));
    }

    public function update(UpdateBrandProfileRequest $request): JsonResponse
    {
        return $this->response->success($this->service->update($request->toDTO()));
    }

    public function destroy(string $id): Response
    {
        $this->service->delete((int) $id, $this->context->accountId);

        return $this->response->noContent();
    }
}
