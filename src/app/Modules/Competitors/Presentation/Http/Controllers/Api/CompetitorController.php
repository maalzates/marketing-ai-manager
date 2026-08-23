<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Presentation\Http\Controllers\Api;

use App\Modules\Competitors\Application\Services\CompetitorService;
use App\Modules\Competitors\Presentation\Http\Requests\IndexCompetitorPostRequest;
use App\Modules\Competitors\Presentation\Http\Requests\IndexCompetitorRequest;
use App\Modules\Competitors\Presentation\Http\Requests\StoreCompetitorRequest;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CompetitorController extends ApiController
{
    public function __construct(
        private readonly CompetitorService $service,
        private readonly AccountContext $context,
    ) {
        parent::__construct();
    }

    public function index(IndexCompetitorRequest $request): JsonResponse
    {
        return $this->response->success($this->service->forAccount($request->toDTO()));
    }

    public function show(string $id): JsonResponse
    {
        return $this->response->success($this->service->find((int) $id, $this->context->accountId));
    }

    public function store(StoreCompetitorRequest $request): JsonResponse
    {
        return $this->response->created($this->service->create($request->toDTO()));
    }

    public function destroy(string $id): Response
    {
        $this->service->delete((int) $id, $this->context->accountId);

        return $this->response->noContent();
    }

    /** 202: the scrape is queued. The caller keeps reading the rows it already has. */
    public function sync(string $id): JsonResponse
    {
        return $this->response->accepted($this->service->requestSync((int) $id, $this->context->accountId));
    }

    public function posts(IndexCompetitorPostRequest $request): JsonResponse
    {
        return $this->response->success($this->service->postsFor($request->toDTO()));
    }
}
