<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Competitors\Domain\Enums\CompetitorPlatform;
use App\Modules\Competitors\Infrastructure\Persistence\Competitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Competitor>
 */
class CompetitorFactory extends Factory
{
    protected $model = Competitor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'strategy_id' => null,
            'platform' => CompetitorPlatform::Instagram,
            'handle' => fake()->unique()->userName(),
            'external_id' => (string) fake()->randomNumber(8),
            'display_name' => fake()->company(),
            'is_active' => true,
            'last_synced_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }

    public function facebookAds(): static
    {
        return $this->state(fn (array $attributes): array => ['platform' => CompetitorPlatform::FacebookAds]);
    }
}
