<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Experiments\Domain\Enums\ExperimentPlatform;
use App\Modules\Experiments\Domain\Enums\ExperimentStatus;
use App\Modules\Experiments\Domain\Enums\ExperimentType;
use App\Modules\Experiments\Domain\Enums\Verdict;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Experiment>
 */
class ExperimentFactory extends Factory
{
    protected $model = Experiment::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'strategy_id' => Strategy::factory(),
            'code' => 'EXP-'.str_pad((string) fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'type' => ExperimentType::Ads,
            'platform' => ExperimentPlatform::Instagram,
            'title' => fake()->sentence(4),
            'hypothesis' => fake()->sentence(10),
            'expected_result' => ['metric' => 'cpa', 'operator' => 'lte', 'value' => 20.0],
            'starts_at' => CarbonImmutable::now()->startOfDay(),
            'ends_at' => CarbonImmutable::now()->startOfDay()->addDays(14),
            'max_budget' => 500.00,
            'configuration' => [],
            'status' => ExperimentStatus::Draft,
            'spend_total' => 0,
            'learning_phase_ends_at' => CarbonImmutable::now()->startOfDay()->addDays(7),
            'closed_early' => false,
        ];
    }

    public function organic(): self
    {
        return $this->state(fn (): array => [
            'type' => ExperimentType::Organic,
            'max_budget' => null,
            'learning_phase_ends_at' => null,
            'expected_result' => ['metric' => 'engagement_rate', 'operator' => 'gte', 'value' => 3.0],
        ]);
    }

    public function running(): self
    {
        return $this->state(fn (): array => ['status' => ExperimentStatus::Running]);
    }

    public function completed(): self
    {
        return $this->state(fn (): array => ['status' => ExperimentStatus::Completed]);
    }

    public function withVerdict(Verdict $verdict = Verdict::Worked): self
    {
        return $this->state(fn (): array => [
            'status' => ExperimentStatus::Completed,
            'verdict' => $verdict,
            'verdict_reason' => fake()->sentence(8),
            'verdict_confirmed_at' => CarbonImmutable::now(),
        ]);
    }

    public function closedEarly(): self
    {
        return $this->state(fn (): array => ['closed_early' => true]);
    }
}
