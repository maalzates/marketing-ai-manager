<?php

declare(strict_types=1);

namespace App\Modules\Content\Presentation\Http\Controllers\Api;

use App\Modules\Content\Application\Jobs\GenerateContentScriptsJob;
use App\Modules\Content\Application\Services\ContentPlanService;
use App\Modules\Content\Application\Services\ContentScriptService;
use App\Modules\Content\Presentation\Http\Requests\ApproveContentScriptRequest;
use App\Modules\Content\Presentation\Http\Requests\GenerateContentScriptsRequest;
use App\Modules\Content\Presentation\Http\Requests\IndexContentScriptRequest;
use App\Modules\Content\Presentation\Http\Requests\StoreContentScriptRequest;
use App\Modules\Content\Presentation\Http\Requests\UpdateContentScriptRequest;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\JsonResponse;

class ContentScriptController extends ApiController
{
    public function __construct(
        private readonly ContentScriptService $service,
        private readonly ContentPlanService $planner,
        private readonly AccountContext $context,
    ) {
        parent::__construct();
    }

    public function index(IndexContentScriptRequest $request): JsonResponse
    {
        return $this->response->success($this->service->forAccount($request->toDTO()));
    }

    public function show(string $id): JsonResponse
    {
        return $this->response->success($this->service->find((int) $id, $this->context->accountId));
    }

    public function store(StoreContentScriptRequest $request): JsonResponse
    {
        return $this->response->created($this->service->create($request->toDTO()));
    }

    public function update(UpdateContentScriptRequest $request): JsonResponse
    {
        return $this->response->success($this->service->update($request->toDTO()));
    }

    /** Generation calls the model, so it is queued: the request never waits on a provider. */
    public function generate(GenerateContentScriptsRequest $request, Dispatcher $bus): JsonResponse
    {
        $bus->dispatch(new GenerateContentScriptsJob($request->toDTO()));

        return $this->response->accepted(['status' => 'queued']);
    }

    public function approve(ApproveContentScriptRequest $request): JsonResponse
    {
        return $this->response->success($this->planner->approve($request->toDTO()));
    }
}
