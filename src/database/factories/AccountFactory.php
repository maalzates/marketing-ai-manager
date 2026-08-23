<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'owner_user_id' => User::factory(),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'sandbox_mode' => false,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }

    public function sandbox(): static
    {
        return $this->state(fn (array $attributes): array => ['sandbox_mode' => true]);
    }
}
