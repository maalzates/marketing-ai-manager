<?php

declare(strict_types=1);

namespace App\Modules\Chat\Infrastructure\Repositories;

use App\Modules\Chat\Application\DTO\ConversationFilterDTO;
use App\Modules\Chat\Application\DTO\StartConversationDTO;
use App\Modules\Chat\Domain\Contracts\ChatConversationRepositoryInterface;
use App\Modules\Chat\Domain\Exceptions\ChatConversationPersistenceFailedException;
use App\Modules\Chat\Infrastructure\Persistence\ChatConversation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Throwable;

readonly class ChatConversationRepository implements ChatConversationRepositoryInterface
{
    public function __construct(private ChatConversation $model) {}

    public function findAll(ConversationFilterDTO $filters): Collection|LengthAwarePaginator
    {
        $query = $this->model->newQuery()
            ->where('account_id', $filters->accountId)
            ->where('user_id', $filters->userId)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        return $filters->perPage > 0
            ? $query->paginate(perPage: $filters->perPage, page: $filters->page)
            : $query->get();
    }

    /** A conversation is personal: the account scope alone would expose a teammate's chat. */
    public function findById(int $id, int $accountId, int $userId): ?ChatConversation
    {
        return $this->model->newQuery()
            ->where('account_id', $accountId)
            ->where('user_id', $userId)
            ->find($id);
    }

    public function create(StartConversationDTO $dto): ChatConversation
    {
        try {
            return $this->model->newQuery()->create([
                'account_id' => $dto->accountId,
                'user_id' => $dto->userId,
                'title' => $dto->title,
                'last_message_at' => now(),
            ]);
        } catch (Throwable $exception) {
            throw ChatConversationPersistenceFailedException::wrap(
                $exception,
                context: ['account_id' => $dto->accountId, 'user_id' => $dto->userId],
            );
        }
    }

    public function markActivity(ChatConversation $conversation, ?string $title): ChatConversation
    {
        try {
            $conversation->update(array_filter([
                'last_message_at' => now(),
                'title' => $conversation->title ?? $title,
            ], static fn (mixed $value): bool => $value !== null));

            return $conversation->refresh();
        } catch (Throwable $exception) {
            throw ChatConversationPersistenceFailedException::wrap(
                $exception,
                context: ['chat_conversation_id' => $conversation->id],
            );
        }
    }

    public function replaceSummary(ChatConversation $conversation, string $summary): ChatConversation
    {
        try {
            $conversation->update(['summary' => $summary]);

            return $conversation->refresh();
        } catch (Throwable $exception) {
            throw ChatConversationPersistenceFailedException::wrap(
                $exception,
                context: ['chat_conversation_id' => $conversation->id],
            );
        }
    }

    public function delete(ChatConversation $conversation): bool
    {
        return (bool) $conversation->delete();
    }
}
