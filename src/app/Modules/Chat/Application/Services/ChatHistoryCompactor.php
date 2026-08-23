<?php

declare(strict_types=1);

namespace App\Modules\Chat\Application\Services;

use App\Modules\Ai\Application\DTO\AiRequestDTO;
use App\Modules\Ai\Application\Services\AiService;
use App\Modules\Ai\Domain\Enums\AiTask;
use App\Modules\Chat\Domain\Contracts\ChatConversationRepositoryInterface;
use App\Modules\Chat\Domain\Contracts\ChatMessageRepositoryInterface;
use App\Modules\Chat\Infrastructure\Persistence\ChatConversation;
use App\Modules\Chat\Infrastructure\Persistence\ChatMessage;
use App\Modules\Settings\Application\Services\SettingsService;
use Illuminate\Support\Collection;

/**
 * Sliding window plus running summary. The database keeps every turn — the user can still
 * read the whole conversation — but the prompt only ever carries the newest N turns and one
 * compacted summary of everything before them, so a long chat stops growing its own cost.
 */
readonly class ChatHistoryCompactor
{
    private const string WINDOW_KEY = 'chat.history_window_messages';

    private const string SUMMARY_MAX_TOKENS_KEY = 'chat.summary_max_tokens';

    private const string SUMMARY_INSTRUCTION = <<<'TEXT'
        Compacta la conversación en un resumen breve en español. Conserva únicamente lo que
        el asistente necesita para seguir la conversación: decisiones tomadas, datos
        concretos consultados, propuestas creadas y preferencias expresadas por el usuario.
        Descarta cortesías y repeticiones. Responde solo con el resumen.
        TEXT;

    public function __construct(
        private ChatMessageRepositoryInterface $messages,
        private ChatConversationRepositoryInterface $conversations,
        private SettingsService $settings,
        private AiService $ai,
    ) {}

    /**
     * @return Collection<int, ChatMessage>
     */
    public function window(ChatConversation $conversation): Collection
    {
        return $this->messages->windowForConversation(
            (int) $conversation->id,
            (int) $conversation->account_id,
            $this->windowSize((int) $conversation->account_id),
        );
    }

    /** How much of the conversation the current summary already covers. */
    public function compactedCount(ChatConversation $conversation): int
    {
        return $this->messages->countBeyondWindow(
            (int) $conversation->id,
            (int) $conversation->account_id,
            $this->windowSize((int) $conversation->account_id),
        );
    }

    public function compact(ChatConversation $conversation, int $alreadyCompacted): ChatConversation
    {
        $dropped = $this->messages->beyondWindow(
            (int) $conversation->id,
            (int) $conversation->account_id,
            $this->windowSize((int) $conversation->account_id),
        )->slice($alreadyCompacted);

        return $dropped->isEmpty()
            ? $conversation
            : $this->conversations->replaceSummary($conversation, $this->summarise($conversation, $dropped));
    }

    /**
     * Everything variable travels in the prompt, never in the context block: the context
     * block is the cached head shared by every chat call, and one summarisation must not
     * evict it.
     *
     * @param  Collection<int, ChatMessage>  $dropped
     */
    private function summarise(ChatConversation $conversation, Collection $dropped): string
    {
        return (string) $this->ai->complete(new AiRequestDTO(
            (int) $conversation->account_id,
            AiTask::Chat,
            self::SUMMARY_INSTRUCTION
                ."\n\nResumen previo:\n".($conversation->summary ?? '(ninguno)')
                ."\n\nTurnos a compactar:\n".self::plainText($dropped),
            userId: (int) $conversation->user_id,
            maxTokens: (int) $this->settings->get(self::SUMMARY_MAX_TOKENS_KEY, (int) $conversation->account_id),
        ))->text;
    }

    /**
     * @param  Collection<int, ChatMessage>  $messages
     */
    private static function plainText(Collection $messages): string
    {
        return $messages
            ->map(static fn (ChatMessage $message): string => ChatTranscript::toPlainText($message))
            ->implode("\n");
    }

    private function windowSize(int $accountId): int
    {
        return (int) $this->settings->get(self::WINDOW_KEY, $accountId);
    }
}
