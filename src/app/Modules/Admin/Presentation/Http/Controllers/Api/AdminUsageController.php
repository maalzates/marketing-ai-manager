<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Controllers\Api;

use App\Modules\Admin\Application\Services\AdminUsageService;
use App\Modules\Admin\Presentation\Http\Requests\IndexGlobalUsageRequest;
use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;

class AdminUsageController extends ApiController
{
    public function __construct(private readonly AdminUsageService $service)
    {
        parent::__construct();
    }

    public function index(IndexGlobalUsageRequest $request): JsonResponse
    {
        return $this->response->success($this->service->summary($request->toDTO()));
    }
}
