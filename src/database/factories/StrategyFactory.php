<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Brands\Infrastructure\Persistence\BrandProfile;
use App\Modules\Strategies\Domain\Enums\StrategyStatus;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Strategy>
 */
class StrategyFactory extends Factory
{
    protected $model = Strategy::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            // The brand profile has to belong to the same account, or every strategy built
            // by this factory would already violate the tenancy rule it is used to test.
            'brand_profile_id' => fn (array $attributes): int => BrandProfile::factory()
                ->create(['account_id' => $attributes['account_id']])
                ->id,
            'name' => fake()->words(3, true),
            'objective' => fake()->sentence(),
            'north_star_metric' => fake()->randomElement(['followers_per_week', 'cost_per_lead', 'ctr']),
            'monthly_budget' => fake()->randomFloat(2, 100, 1000),
            'constraints' => fake()->words(2),
            'guardian_config' => [
                'enabled' => true,
                'frequency_days' => 1,
                'reports_enabled' => true,
                'anomaly_multiplier' => 3,
            ],
            'organic_cadence' => ['posts_per_week' => 3, 'preferred_hours' => []],
            'status' => StrategyStatus::Active,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => StrategyStatus::Active]);
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => StrategyStatus::Paused]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => StrategyStatus::Archived]);
    }
}
