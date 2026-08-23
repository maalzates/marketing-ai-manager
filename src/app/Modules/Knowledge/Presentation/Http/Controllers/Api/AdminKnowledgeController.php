<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Presentation\Http\Controllers\Api;

use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use App\Modules\Knowledge\Application\Services\KnowledgeService;
use App\Modules\Knowledge\Presentation\Http\Requests\IndexAdminKnowledgeRequest;
use App\Modules\Knowledge\Presentation\Http\Requests\StoreKnowledgeEntryRequest;
use App\Modules\Knowledge\Presentation\Http\Requests\UpdateKnowledgeEntryRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AdminKnowledgeController extends ApiController
{
    public function __construct(private readonly KnowledgeService $service)
    {
        parent::__construct();
    }

    public function index(IndexAdminKnowledgeRequest $request): JsonResponse
    {
        return $this->response->success($this->service->findAll($request->toDTO()));
    }

    public function store(StoreKnowledgeEntryRequest $request): JsonResponse
    {
        return $this->response->created($this->service->create($request->toDTO()));
    }

    public function update(UpdateKnowledgeEntryRequest $request): JsonResponse
    {
        return $this->response->success($this->service->update($request->toDTO()));
    }

    public function destroy(string $id): Response
    {
        $this->service->delete((int) $id);

        return $this->response->noContent();
    }
}
