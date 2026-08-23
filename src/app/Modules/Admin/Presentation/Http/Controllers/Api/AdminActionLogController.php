<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Controllers\Api;

use App\Modules\Admin\Application\Services\AdminUsageService;
use App\Modules\Admin\Presentation\Http\Requests\IndexGlobalActionLogRequest;
use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;

class AdminActionLogController extends ApiController
{
    public function __construct(private readonly AdminUsageService $service)
    {
        parent::__construct();
    }

    public function __invoke(IndexGlobalActionLogRequest $request): JsonResponse
    {
        return $this->response->success($this->service->actionLogs($request->toDTO()));
    }
}
