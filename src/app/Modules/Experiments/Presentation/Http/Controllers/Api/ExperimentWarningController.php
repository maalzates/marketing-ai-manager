<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Presentation\Http\Controllers\Api;

use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use App\Modules\Experiments\Application\Services\ExperimentService;
use App\Modules\Experiments\Presentation\Http\Requests\ExperimentScopeRequest;
use Illuminate\Http\JsonResponse;

class ExperimentWarningController extends ApiController
{
    public function __construct(private readonly ExperimentService $service)
    {
        parent::__construct();
    }

    public function __invoke(ExperimentScopeRequest $request): JsonResponse
    {
        return $this->response->success($this->service->warningsFor($request->experimentId(), $request->accountId()));
    }
}
