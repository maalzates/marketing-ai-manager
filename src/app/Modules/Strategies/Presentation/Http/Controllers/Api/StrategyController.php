<?php

declare(strict_types=1);

namespace App\Modules\Strategies\Presentation\Http\Controllers\Api;

use App\Modules\Audit\Domain\Enums\ActionOrigin;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use App\Modules\Strategies\Application\DTO\ArchiveStrategyDTO;
use App\Modules\Strategies\Application\Services\StrategyService;
use App\Modules\Strategies\Presentation\Http\Requests\IndexStrategyRequest;
use App\Modules\Strategies\Presentation\Http\Requests\StoreStrategyRequest;
use App\Modules\Strategies\Presentation\Http\Requests\UpdateStrategyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class StrategyController extends ApiController
{
    public function __construct(
        private readonly StrategyService $service,
        private readonly AccountContext $context,
    ) {
        parent::__construct();
    }

    public function index(IndexStrategyRequest $request): JsonResponse
    {
        return $this->response->success($this->service->forAccount($request->toDTO()));
    }

    public function show(string $id): JsonResponse
    {
        return $this->response->success($this->service->find((int) $id, $this->context->accountId));
    }

    public function store(StoreStrategyRequest $request): JsonResponse
    {
        return $this->response->created($this->service->create($request->toDTO()));
    }

    public function update(UpdateStrategyRequest $request): JsonResponse
    {
        return $this->response->success($this->service->update($request->toDTO()));
    }

    public function destroy(string $id): Response
    {
        $this->service->delete((int) $id, $this->context->accountId);

        return $this->response->noContent();
    }

    public function activate(string $id): JsonResponse
    {
        return $this->response->success($this->service->activate((int) $id, $this->context->accountId));
    }

    public function pause(string $id): JsonResponse
    {
        return $this->response->success($this->service->pause((int) $id, $this->context->accountId));
    }

    public function archive(string $id): JsonResponse
    {
        return $this->response->success($this->service->archive(new ArchiveStrategyDTO(
            $this->context->accountId,
            (int) $id,
            $this->context->userId,
            ActionOrigin::UI,
        )));
    }
}
