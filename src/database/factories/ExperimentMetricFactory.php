<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use App\Modules\Experiments\Infrastructure\Persistence\ExperimentMetric;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExperimentMetric>
 */
class ExperimentMetricFactory extends Factory
{
    protected $model = ExperimentMetric::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'experiment_id' => Experiment::factory(),
            'date' => CarbonImmutable::now()->startOfDay(),
            'spend' => 50.00,
            'impressions' => 10000,
            'reach' => 8000,
            'clicks' => 150,
            'ctr' => 1.5,
            'cpm' => 5.0,
            'cpc' => 0.33,
            'conversions' => 5,
            'cpa' => 10.0,
            'frequency' => 1.25,
            'video_views' => 2000,
            'engagement' => 300,
            'raw' => [],
        ];
    }

    public function onDate(CarbonImmutable $date): self
    {
        return $this->state(fn (): array => ['date' => $date->startOfDay()]);
    }

    public function withoutConversions(): self
    {
        return $this->state(fn (): array => ['conversions' => 0, 'cpa' => null]);
    }
}
