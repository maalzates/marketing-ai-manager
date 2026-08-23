<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Presentation\Http\Controllers\Api;

use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use App\Modules\Reporting\Application\Services\ReportService;
use App\Modules\Reporting\Presentation\Http\Requests\IndexReportRequest;
use Illuminate\Http\JsonResponse;

class ReportController extends ApiController
{
    public function __construct(
        private readonly ReportService $service,
        private readonly AccountContext $context,
    ) {
        parent::__construct();
    }

    public function index(IndexReportRequest $request): JsonResponse
    {
        return $this->response->success($this->service->list($request->toDTO()));
    }

    public function show(string $id): JsonResponse
    {
        return $this->response->success($this->service->find((int) $id, $this->context->accountId));
    }
}
