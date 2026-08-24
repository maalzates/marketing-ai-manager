<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Presentation\Http\Controllers\Api;

use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use App\Modules\Experiments\Application\Services\VerdictService;
use App\Modules\Experiments\Presentation\Http\Requests\ConfirmVerdictRequest;
use Illuminate\Http\JsonResponse;

class ExperimentVerdictController extends ApiController
{
    public function __construct(private readonly VerdictService $service)
    {
        parent::__construct();
    }

    public function store(ConfirmVerdictRequest $request): JsonResponse
    {
        return $this->response->success($this->service->confirm(
            $request->experimentId(),
            $request->accountId(),
            $request->verdict(),
            $request->reason(),
            $request->userId(),
        ));
    }
}
