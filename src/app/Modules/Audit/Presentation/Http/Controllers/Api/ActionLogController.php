<?php

declare(strict_types=1);

namespace App\Modules\Audit\Presentation\Http\Controllers\Api;

use App\Modules\Audit\Application\Services\ActionLogService;
use App\Modules\Audit\Presentation\Http\Requests\IndexActionLogRequest;
use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;

class ActionLogController extends ApiController
{
    public function __construct(private readonly ActionLogService $service)
    {
        parent::__construct();
    }

    public function index(IndexActionLogRequest $request): JsonResponse
    {
        return $this->response->success($this->service->findAll($request->toDTO()));
    }
}
