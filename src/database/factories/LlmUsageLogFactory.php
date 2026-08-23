<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Audit\Infrastructure\Persistence\LlmUsageLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LlmUsageLog>
 */
class LlmUsageLogFactory extends Factory
{
    protected $model = LlmUsageLog::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'user_id' => User::factory(),
            'feature' => fake()->randomElement(['chat', 'guardian', 'verdict', 'comment_mining']),
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-5',
            'input_tokens' => fake()->numberBetween(100, 5000),
            'output_tokens' => fake()->numberBetween(50, 2000),
            'reasoning_tokens' => 0,
            'cached_input_tokens' => 0,
            'estimated_cost_usd' => fake()->randomFloat(6, 0.0001, 0.5),
        ];
    }
}
