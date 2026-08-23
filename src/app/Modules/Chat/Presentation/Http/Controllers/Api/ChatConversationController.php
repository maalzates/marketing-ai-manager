<?php

declare(strict_types=1);

namespace App\Modules\Chat\Presentation\Http\Controllers\Api;

use App\Modules\Chat\Application\Services\ChatService;
use App\Modules\Chat\Presentation\Http\Requests\IndexConversationRequest;
use App\Modules\Chat\Presentation\Http\Requests\StoreConversationRequest;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ChatConversationController extends ApiController
{
    public function __construct(
        private readonly ChatService $service,
        private readonly AccountContext $context,
    ) {
        parent::__construct();
    }

    public function index(IndexConversationRequest $request): JsonResponse
    {
        return $this->response->success($this->service->forUser($request->toDTO()));
    }

    public function store(StoreConversationRequest $request): JsonResponse
    {
        return $this->response->created($this->service->start($request->toDTO()));
    }

    public function show(string $id): JsonResponse
    {
        return $this->response->success(
            $this->service->conversation((int) $id, $this->context->accountId, $this->context->userId),
        );
    }

    public function destroy(string $id): Response
    {
        $this->service->delete((int) $id, $this->context->accountId, $this->context->userId);

        return $this->response->noContent();
    }
}
