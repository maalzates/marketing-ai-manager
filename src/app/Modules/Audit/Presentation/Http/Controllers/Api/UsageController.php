<?php

declare(strict_types=1);

namespace App\Modules\Audit\Presentation\Http\Controllers\Api;

use App\Modules\Audit\Application\Services\UsageService;
use App\Modules\Audit\Presentation\Http\Requests\IndexUsageRequest;
use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;

class UsageController extends ApiController
{
    public function __construct(private readonly UsageService $service)
    {
        parent::__construct();
    }

    public function __invoke(IndexUsageRequest $request): JsonResponse
    {
        return $this->response->success($this->service->summary($request->toDTO()));
    }
}
