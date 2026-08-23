<?php

declare(strict_types=1);

namespace App\Modules\Chat\Application\Services;

use App\Modules\Ai\Application\DTO\Messages\TextMessage;
use App\Modules\Ai\Application\DTO\Messages\ToolCallMessage;
use App\Modules\Ai\Application\DTO\Messages\ToolResultMessage;
use App\Modules\Ai\Domain\Contracts\LlmMessageInterface;
use App\Modules\Ai\Domain\Enums\MessageRole as LlmMessageRole;
use App\Modules\Chat\Domain\Enums\MessageRole;
use App\Modules\Chat\Infrastructure\Persistence\ChatMessage;
use Illuminate\Support\Collection;

/**
 * Rebuilds the model's own turns from the rows they were stored as. One tool call is one
 * row, so a turn that used several tools spans several rows; they are regrouped here into
 * the single ToolCallMessage the model produced, and their results into one
 * ToolResultMessage — which is the only shape every provider can translate.
 */
readonly class ChatTranscript
{
    private const string USER = 'user';

    private const string ASSISTANT_TEXT = 'assistant_text';

    private const string TOOL_CALL = 'tool_call';

    private const string TOOL_RESULT = 'tool_result';

    /**
     * @param  Collection<int, ChatMessage>  $messages
     * @return list<LlmMessageInterface>
     */
    public function fromMessages(Collection $messages): array
    {
        return $messages
            ->chunkWhile(static fn (ChatMessage $message, int $key, Collection $chunk): bool => self::continues(
                $message,
                $chunk->last(),
            ))
            ->map(static fn (Collection $chunk): LlmMessageInterface => self::toMessage($chunk))
            ->values()
            ->all();
    }

    /** An assistant text row belongs to the tool call that follows it: they were one turn. */
    private static function continues(ChatMessage $message, ChatMessage $previous): bool
    {
        return match (self::kind($message)) {
            self::TOOL_RESULT => self::kind($previous) === self::TOOL_RESULT,
            self::TOOL_CALL => in_array(self::kind($previous), [self::TOOL_CALL, self::ASSISTANT_TEXT], true),
            default => false,
        };
    }

    /**
     * @param  Collection<int, ChatMessage>  $chunk
     */
    private static function toMessage(Collection $chunk): LlmMessageInterface
    {
        return match (self::kind($chunk->last())) {
            self::TOOL_CALL => new ToolCallMessage(
                $chunk->firstWhere('tool_use_id', null)?->content,
                self::calls($chunk),
            ),
            self::TOOL_RESULT => new ToolResultMessage(self::results($chunk)),
            self::USER => new TextMessage(LlmMessageRole::User, (string) $chunk->first()->content),
            default => new TextMessage(LlmMessageRole::Assistant, (string) $chunk->first()->content),
        };
    }

    /**
     * @param  Collection<int, ChatMessage>  $chunk
     * @return list<array{id: string, name: string, input: array}>
     */
    private static function calls(Collection $chunk): array
    {
        return $chunk
            ->filter(static fn (ChatMessage $message): bool => $message->tool_use_id !== null)
            ->map(static fn (ChatMessage $message): array => [
                'id' => (string) $message->tool_use_id,
                'name' => (string) $message->tool_name,
                'input' => $message->tool_input ?? [],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ChatMessage>  $chunk
     * @return list<array{id: string, name: string, content: string, is_error: bool}>
     */
    private static function results(Collection $chunk): array
    {
        return $chunk
            ->map(static fn (ChatMessage $message): array => [
                'id' => (string) $message->tool_use_id,
                'name' => (string) $message->tool_name,
                'content' => json_encode(
                    $message->tool_result ?? [],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ),
                'is_error' => array_key_exists(ToolInvoker::ERROR_KEY, $message->tool_result ?? []),
            ])
            ->values()
            ->all();
    }

    private static function kind(ChatMessage $message): string
    {
        return match (true) {
            $message->role === MessageRole::Tool => self::TOOL_RESULT,
            $message->role === MessageRole::User => self::USER,
            $message->tool_use_id !== null => self::TOOL_CALL,
            default => self::ASSISTANT_TEXT,
        };
    }

    /** The plain-text rendering the compactor feeds the summariser. */
    public static function toPlainText(ChatMessage $message): string
    {
        return match (self::kind($message)) {
            self::USER => 'user: '.$message->content,
            self::ASSISTANT_TEXT => 'assistant: '.$message->content,
            self::TOOL_CALL => 'assistant: called '.$message->tool_name
                .' with '.json_encode($message->tool_input ?? []),
            default => 'tool '.$message->tool_name.' returned: '.json_encode($message->tool_result ?? []),
        };
    }
}
