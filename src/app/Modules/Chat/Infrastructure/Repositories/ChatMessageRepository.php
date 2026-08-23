<?php

declare(strict_types=1);

namespace App\Modules\Chat\Infrastructure\Repositories;

use App\Modules\Chat\Application\DTO\CreateChatMessageDTO;
use App\Modules\Chat\Domain\Contracts\ChatMessageRepositoryInterface;
use App\Modules\Chat\Domain\Exceptions\ChatMessagePersistenceFailedException;
use App\Modules\Chat\Infrastructure\Persistence\ChatMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

readonly class ChatMessageRepository implements ChatMessageRepositoryInterface
{
    public function __construct(private ChatMessage $model) {}

    public function findForConversation(int $conversationId, int $accountId): Collection
    {
        return $this->query($conversationId, $accountId)->orderBy('id')->get();
    }

    public function windowForConversation(int $conversationId, int $accountId, int $limit): Collection
    {
        return $this->query($conversationId, $accountId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->sortBy('id')
            ->values();
    }

    public function beyondWindow(int $conversationId, int $accountId, int $limit): Collection
    {
        return $this->query($conversationId, $accountId)
            ->where('id', '<', $this->windowStartId($conversationId, $accountId, $limit))
            ->orderBy('id')
            ->get();
    }

    public function countBeyondWindow(int $conversationId, int $accountId, int $limit): int
    {
        return $this->query($conversationId, $accountId)
            ->where('id', '<', $this->windowStartId($conversationId, $accountId, $limit))
            ->count();
    }

    public function create(CreateChatMessageDTO $dto): ChatMessage
    {
        try {
            return $this->model->newQuery()->create([
                'account_id' => $dto->accountId,
                'chat_conversation_id' => $dto->conversationId,
                'role' => $dto->role,
                'content' => $dto->content,
                'tool_name' => $dto->toolName,
                'tool_use_id' => $dto->toolUseId,
                'tool_input' => $dto->toolInput,
                'tool_result' => $dto->toolResult,
                'input_tokens' => $dto->inputTokens,
                'output_tokens' => $dto->outputTokens,
            ]);
        } catch (Throwable $exception) {
            throw ChatMessagePersistenceFailedException::wrap($exception, context: [
                'chat_conversation_id' => $dto->conversationId,
                'role' => $dto->role->value,
            ]);
        }
    }

    /** 0 when the conversation is shorter than the window, so nothing falls outside it. */
    private function windowStartId(int $conversationId, int $accountId, int $limit): int
    {
        return (int) $this->query($conversationId, $accountId)
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('id')
            ->min();
    }

    private function query(int $conversationId, int $accountId): Builder
    {
        return $this->model->newQuery()
            ->where('account_id', $accountId)
            ->where('chat_conversation_id', $conversationId);
    }
}
