<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Content\Domain\Enums\ScheduleStatus;
use App\Modules\Content\Infrastructure\Persistence\ContentSchedule;
use App\Modules\Experiments\Domain\Enums\ExperimentPlatform;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentSchedule>
 */
class ContentScheduleFactory extends Factory
{
    protected $model = ContentSchedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            // Experiment, strategy and schedule all have to sit in the same account, or the
            // factory itself would break the tenancy rule its tests exist to prove.
            'experiment_id' => fn (array $attributes): int => Experiment::factory()
                ->organic()
                ->create([
                    'account_id' => $attributes['account_id'],
                    'strategy_id' => Strategy::factory()->create(['account_id' => $attributes['account_id']])->id,
                ])
                ->id,
            'asset_id' => null,
            'platform' => ExperimentPlatform::Instagram,
            'scheduled_at' => CarbonImmutable::now()->addDay(),
            'published_at' => null,
            'status' => ScheduleStatus::Pending,
            'attempts' => 0,
            'last_error' => null,
            'external_post_id' => null,
        ];
    }

    public function due(): static
    {
        return $this->state(fn (array $attributes): array => [
            'scheduled_at' => CarbonImmutable::now()->subMinute(),
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ScheduleStatus::Published,
            'published_at' => CarbonImmutable::now()->subDay(),
            'external_post_id' => (string) fake()->randomNumber(8, true),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ScheduleStatus::Failed,
            'attempts' => 3,
            'last_error' => 'manual_publish_required',
        ]);
    }
}
