<?php

declare(strict_types=1);

namespace App\Modules\Chat\Domain\Contracts;

use App\Modules\Chat\Application\DTO\CreateChatMessageDTO;
use App\Modules\Chat\Infrastructure\Persistence\ChatMessage;
use Illuminate\Support\Collection;

interface ChatMessageRepositoryInterface
{
    /**
     * @return Collection<int, ChatMessage>
     */
    public function findForConversation(int $conversationId, int $accountId): Collection;

    /**
     * The sliding window: the newest $limit messages, already in chronological order.
     *
     * @return Collection<int, ChatMessage>
     */
    public function windowForConversation(int $conversationId, int $accountId, int $limit): Collection;

    /**
     * Everything the window leaves behind — the input to the running summary.
     *
     * @return Collection<int, ChatMessage>
     */
    public function beyondWindow(int $conversationId, int $accountId, int $limit): Collection;

    public function countBeyondWindow(int $conversationId, int $accountId, int $limit): int;

    public function create(CreateChatMessageDTO $dto): ChatMessage;
}
