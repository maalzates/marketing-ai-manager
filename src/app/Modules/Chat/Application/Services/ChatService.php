<?php

declare(strict_types=1);

namespace App\Modules\Chat\Application\Services;

use App\Modules\Ai\Application\DTO\AiRequestDTO;
use App\Modules\Ai\Application\DTO\LlmResponseDTO;
use App\Modules\Ai\Application\Services\AiService;
use App\Modules\Ai\Domain\Enums\AiTask;
use App\Modules\Chat\Application\DTO\ConversationFilterDTO;
use App\Modules\Chat\Application\DTO\CreateChatMessageDTO;
use App\Modules\Chat\Application\DTO\SendChatMessageDTO;
use App\Modules\Chat\Application\DTO\StartConversationDTO;
use App\Modules\Chat\Domain\Contracts\ChatConversationRepositoryInterface;
use App\Modules\Chat\Domain\Contracts\ChatMessageRepositoryInterface;
use App\Modules\Chat\Domain\Enums\MessageRole;
use App\Modules\Chat\Domain\Exceptions\ChatConversationNotFoundException;
use App\Modules\Chat\Domain\Exceptions\ChatDisabledException;
use App\Modules\Chat\Domain\Exceptions\ChatToolLoopLimitExceededException;
use App\Modules\Chat\Infrastructure\Persistence\ChatConversation;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Tools\ToolRegistry;
use App\Modules\Settings\Application\Services\SettingsService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The tool loop: send the history plus every registered tool definition, execute whatever
 * the model asks for, feed the result back, repeat until it answers in text. Every turn is
 * persisted, so the conversation survives the request that produced it.
 */
readonly class ChatService
{
    private const string FEATURE_KEY = 'features.chat';

    private const string MAX_TOOL_ROUND_TRIPS_KEY = 'chat.max_tool_round_trips';

    private const int TITLE_LENGTH = 60;

    public function __construct(
        private ChatConversationRepositoryInterface $conversations,
        private ChatMessageRepositoryInterface $messages,
        private ChatHistoryCompactor $compactor,
        private ChatTranscript $transcript,
        private ToolInvoker $invoker,
        private ToolRegistry $tools,
        private AiService $ai,
        private SettingsService $settings,
    ) {}

    /**
     * @return Collection<int, ChatConversation>|LengthAwarePaginator<int, ChatConversation>
     */
    public function forUser(ConversationFilterDTO $filters): Collection|LengthAwarePaginator
    {
        return $this->conversations->findAll($filters);
    }

    public function conversation(int $id, int $accountId, int $userId): ChatConversation
    {
        return $this->find($id, $accountId, $userId)->load('messages');
    }

    public function start(StartConversationDTO $dto): ChatConversation
    {
        $this->assertEnabled($dto->accountId);

        return $this->conversations->create($dto);
    }

    public function delete(int $id, int $accountId, int $userId): bool
    {
        return $this->conversations->delete($this->find($id, $accountId, $userId));
    }

    public function send(SendChatMessageDTO $dto): ChatConversation
    {
        $this->assertEnabled($dto->accountId);

        $conversation = $dto->conversationId === null
            ? $this->conversations->create(new StartConversationDTO(
                $dto->accountId,
                $dto->userId,
                Str::limit($dto->message, self::TITLE_LENGTH),
            ))
            : $this->find($dto->conversationId, $dto->accountId, $dto->userId);

        // Read before the turn writes anything: what the summary already covers is what falls
        // outside the window now, and the difference afterwards is exactly what to compact.
        $alreadyCompacted = $this->compactor->compactedCount($conversation);

        $this->messages->create(new CreateChatMessageDTO(
            $dto->accountId,
            (int) $conversation->id,
            MessageRole::User,
            $dto->message,
        ));

        $this->runLoop($conversation, $dto);

        return $this->compactor->compact(
            $this->conversations->markActivity($conversation, Str::limit($dto->message, self::TITLE_LENGTH)),
            $alreadyCompacted,
        )->load('messages');
    }

    private function runLoop(ChatConversation $conversation, SendChatMessageDTO $dto): void
    {
        $maxRoundTrips = (int) $this->settings->get(self::MAX_TOOL_ROUND_TRIPS_KEY, $dto->accountId);

        for ($roundTrip = 0; $roundTrip <= $maxRoundTrips; $roundTrip++) {
            $response = $this->ai->complete($this->request($conversation, $dto, $roundTrip));

            $this->persistAssistantTurn($conversation, $response);

            if ($response->toolCalls === []) {
                return;
            }

            $this->persistToolResults($conversation, $dto, $response);
        }

        throw ChatToolLoopLimitExceededException::afterRoundTrips((int) $conversation->id, $maxRoundTrips);
    }

    private function request(ChatConversation $conversation, SendChatMessageDTO $dto, int $roundTrip): AiRequestDTO
    {
        $window = $this->compactor->window($conversation);

        return new AiRequestDTO(
            $dto->accountId,
            AiTask::Chat,
            // After the first pass the history already ends with the tool results, so an
            // empty prompt tells the assembler not to invent a user turn after them.
            $roundTrip === 0 ? $dto->message : '',
            $conversation->summary === null ? [] : ['conversation_summary' => $conversation->summary],
            $this->tools->definitions()->all(),
            // On the first pass the user's message is already in the window and travels as
            // `prompt` instead, so sending both would show the model the same turn twice.
            $this->transcript->fromMessages($roundTrip === 0 ? $window->slice(0, -1) : $window),
            $dto->userId,
        );
    }

    private function persistAssistantTurn(ChatConversation $conversation, LlmResponseDTO $response): void
    {
        collect(self::assistantRows($response))->each(
            fn (array $row, int $index): mixed => $this->messages->create(new CreateChatMessageDTO(
                (int) $conversation->account_id,
                (int) $conversation->id,
                MessageRole::Assistant,
                $row['content'],
                $row['tool_name'],
                $row['tool_use_id'],
                $row['tool_input'],
                null,
                // The provider bills the call once. Hanging it on the first row of the turn
                // keeps the conversation's token sum equal to what the usage ledger recorded.
                $index === 0 ? $response->inputTokens + $response->cachedInputTokens : 0,
                $index === 0 ? $response->outputTokens : 0,
            )),
        );
    }

    /**
     * @return list<array{content: ?string, tool_name: ?string, tool_use_id: ?string, tool_input: ?array}>
     */
    private static function assistantRows(LlmResponseDTO $response): array
    {
        return [
            ...($response->text === null || $response->text === ''
                ? []
                : [['content' => $response->text, 'tool_name' => null, 'tool_use_id' => null, 'tool_input' => null]]),
            ...array_map(static fn (array $call): array => [
                'content' => null,
                'tool_name' => $call['name'],
                'tool_use_id' => $call['id'],
                'tool_input' => $call['input'],
            ], $response->toolCalls),
        ];
    }

    private function persistToolResults(
        ChatConversation $conversation,
        SendChatMessageDTO $dto,
        LlmResponseDTO $response,
    ): void {
        foreach ($response->toolCalls as $call) {
            $this->messages->create(new CreateChatMessageDTO(
                $dto->accountId,
                (int) $conversation->id,
                MessageRole::Tool,
                null,
                $call['name'],
                $call['id'],
                $call['input'],
                $this->invoker->invoke(
                    $call['name'],
                    $call['input'],
                    new AccountContext($dto->accountId, $dto->userId),
                ),
            ));
        }
    }

    private function find(int $id, int $accountId, int $userId): ChatConversation
    {
        return $this->conversations->findById($id, $accountId, $userId)
            ?? throw ChatConversationNotFoundException::withId($id);
    }

    private function assertEnabled(int $accountId): void
    {
        if ($this->settings->get(self::FEATURE_KEY, $accountId) !== true) {
            throw ChatDisabledException::forAccount($accountId);
        }
    }
}
