<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Presentation\Http\Controllers\Api;

use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use App\Modules\Experiments\Application\Services\ExperimentService;
use App\Modules\Experiments\Presentation\Http\Requests\ExperimentScopeRequest;
use App\Modules\Experiments\Presentation\Http\Requests\IndexExperimentRequest;
use App\Modules\Experiments\Presentation\Http\Requests\StoreExperimentRequest;
use App\Modules\Experiments\Presentation\Http\Requests\UpdateExperimentRequest;
use Illuminate\Http\JsonResponse;

class ExperimentController extends ApiController
{
    public function __construct(private readonly ExperimentService $service)
    {
        parent::__construct();
    }

    public function index(IndexExperimentRequest $request): JsonResponse
    {
        return $this->response->success($this->service->forStrategy($request->toDTO()));
    }

    public function store(StoreExperimentRequest $request): JsonResponse
    {
        return $this->response->created($this->service->create($request->toDTO()));
    }

    public function show(ExperimentScopeRequest $request): JsonResponse
    {
        return $this->response->success($this->service->find($request->experimentId(), $request->accountId()));
    }

    public function update(UpdateExperimentRequest $request): JsonResponse
    {
        return $this->response->success($this->service->update($request->toDTO()));
    }
}
