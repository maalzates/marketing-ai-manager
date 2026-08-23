<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Admin\Infrastructure\Persistence\ApplicationApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApplicationApiKey>
 */
class ApplicationApiKeyFactory extends Factory
{
    protected $model = ApplicationApiKey::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plainToken = 'mk_live_'.Str::random(40);

        return [
            'account_id' => Account::factory(),
            'name' => fake()->words(2, true),
            'prefix' => substr($plainToken, 0, 16),
            'token_hash' => hash('sha256', $plainToken),
            'abilities' => ['*'],
            'last_used_at' => null,
            'revoked_at' => null,
            'created_by_user_id' => User::factory(),
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => ['revoked_at' => now()->subDay()]);
    }

    public function used(): static
    {
        return $this->state(fn (array $attributes): array => ['last_used_at' => now()->subHour()]);
    }

    public function platformWide(): static
    {
        return $this->state(fn (array $attributes): array => ['account_id' => null]);
    }
}
