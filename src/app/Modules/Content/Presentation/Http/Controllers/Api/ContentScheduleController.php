<?php

declare(strict_types=1);

namespace App\Modules\Content\Presentation\Http\Controllers\Api;

use App\Modules\Content\Application\Services\PublishingService;
use App\Modules\Content\Presentation\Http\Requests\IndexScheduleRequest;
use App\Modules\Content\Presentation\Http\Requests\LinkRecordingsRequest;
use App\Modules\Content\Presentation\Http\Requests\StoreScheduleRequest;
use App\Modules\Content\Presentation\Http\Requests\UpdateScheduleRequest;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ContentScheduleController extends ApiController
{
    public function __construct(
        private readonly PublishingService $service,
        private readonly AccountContext $context,
    ) {
        parent::__construct();
    }

    public function index(IndexScheduleRequest $request): JsonResponse
    {
        return $this->response->success($this->service->forAccount($request->toDTO()));
    }

    public function store(StoreScheduleRequest $request): JsonResponse
    {
        return $this->response->created($this->service->schedule($request->toDTO()));
    }

    public function update(UpdateScheduleRequest $request): JsonResponse
    {
        return $this->response->success($this->service->update($request->toDTO()));
    }

    public function destroy(string $id): Response
    {
        $this->service->delete((int) $id, $this->context->accountId);

        return $this->response->noContent();
    }

    public function recordings(LinkRecordingsRequest $request): JsonResponse
    {
        return $this->response->success($this->service->linkRecordings($request->toDTO()));
    }
}
