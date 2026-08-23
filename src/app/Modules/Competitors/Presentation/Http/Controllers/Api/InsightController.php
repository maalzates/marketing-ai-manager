<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Presentation\Http\Controllers\Api;

use App\Modules\Competitors\Application\Services\InsightService;
use App\Modules\Competitors\Presentation\Http\Requests\IndexInsightRequest;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;

class InsightController extends ApiController
{
    public function __construct(
        private readonly InsightService $service,
        private readonly AccountContext $context,
    ) {
        parent::__construct();
    }

    public function index(IndexInsightRequest $request): JsonResponse
    {
        return $this->response->success($this->service->forAccount($request->toDTO()));
    }

    public function discard(string $id): JsonResponse
    {
        return $this->response->success($this->service->discard((int) $id, $this->context->accountId));
    }
}
