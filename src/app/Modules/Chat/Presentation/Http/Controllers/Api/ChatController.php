<?php

declare(strict_types=1);

namespace App\Modules\Chat\Presentation\Http\Controllers\Api;

use App\Modules\Chat\Application\Services\ChatService;
use App\Modules\Chat\Presentation\Http\Requests\SendChatMessageRequest;
use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;

/**
 * The whole chat surface is this one call. Every decision — which tools exist, how many
 * round trips are allowed, what gets persisted — lives in ChatService, so a future MCP
 * server reuses the same loop without touching HTTP.
 */
class ChatController extends ApiController
{
    public function __construct(private readonly ChatService $service)
    {
        parent::__construct();
    }

    public function __invoke(SendChatMessageRequest $request): JsonResponse
    {
        return $this->response->success($this->service->send($request->toDTO()));
    }
}
