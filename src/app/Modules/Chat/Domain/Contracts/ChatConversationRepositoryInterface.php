<?php

declare(strict_types=1);

namespace App\Modules\Chat\Domain\Contracts;

use App\Modules\Chat\Application\DTO\ConversationFilterDTO;
use App\Modules\Chat\Application\DTO\StartConversationDTO;
use App\Modules\Chat\Infrastructure\Persistence\ChatConversation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ChatConversationRepositoryInterface
{
    public function findAll(ConversationFilterDTO $filters): Collection|LengthAwarePaginator;

    public function findById(int $id, int $accountId, int $userId): ?ChatConversation;

    public function create(StartConversationDTO $dto): ChatConversation;

    public function markActivity(ChatConversation $conversation, ?string $title): ChatConversation;

    public function replaceSummary(ChatConversation $conversation, string $summary): ChatConversation;

    public function delete(ChatConversation $conversation): bool;
}
