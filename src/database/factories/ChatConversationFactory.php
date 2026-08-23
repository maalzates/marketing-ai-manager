<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Chat\Infrastructure\Persistence\ChatConversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatConversation>
 */
class ChatConversationFactory extends Factory
{
    protected $model = ChatConversation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            // The owner has to belong to the same account, or every conversation built here
            // would already violate the tenancy rule it is used to test.
            'user_id' => fn (array $attributes): int => Account::query()
                ->findOrFail($attributes['account_id'])
                ->owner_user_id,
            'title' => fake()->sentence(4),
            'summary' => null,
            'last_message_at' => now(),
        ];
    }

    public function summarised(string $summary): static
    {
        return $this->state(fn (array $attributes): array => ['summary' => $summary]);
    }
}
