<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Chat\Domain\Enums\MessageRole;
use App\Modules\Chat\Infrastructure\Persistence\ChatConversation;
use App\Modules\Chat\Infrastructure\Persistence\ChatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chat_conversation_id' => ChatConversation::factory(),
            // A message always belongs to its conversation's account; deriving it here keeps
            // the two from drifting apart in a test that only sets the conversation.
            'account_id' => fn (array $attributes): int => ChatConversation::query()
                ->findOrFail($attributes['chat_conversation_id'])
                ->account_id,
            'role' => MessageRole::User,
            'content' => fake()->sentence(),
            'tool_name' => null,
            'tool_use_id' => null,
            'tool_input' => null,
            'tool_result' => null,
            'input_tokens' => 0,
            'output_tokens' => 0,
        ];
    }

    public function assistant(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => MessageRole::Assistant,
            'input_tokens' => fake()->numberBetween(100, 2000),
            'output_tokens' => fake()->numberBetween(10, 500),
        ]);
    }

    public function toolCall(string $toolName): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => MessageRole::Assistant,
            'content' => null,
            'tool_name' => $toolName,
            'tool_use_id' => 'toolu_'.fake()->uuid(),
            'tool_input' => [],
        ]);
    }

    public function toolResult(string $toolName, array $result = []): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => MessageRole::Tool,
            'content' => null,
            'tool_name' => $toolName,
            'tool_use_id' => 'toolu_'.fake()->uuid(),
            'tool_result' => $result,
        ]);
    }
}
