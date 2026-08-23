<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use App\Modules\Reporting\Domain\Enums\ReportType;
use App\Modules\Reporting\Infrastructure\Persistence\Report;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            // Same account as the report, or every row this factory builds would already
            // break the tenancy rule it is used to test.
            'strategy_id' => fn (array $attributes): int => Strategy::factory()
                ->create(['account_id' => $attributes['account_id']])
                ->id,
            'experiment_id' => null,
            'type' => ReportType::Periodic,
            'period_start' => CarbonImmutable::now()->subWeek()->toDateString(),
            'period_end' => CarbonImmutable::now()->toDateString(),
            'body' => fake()->paragraphs(3, true),
            'data' => ['experiments' => []],
            'generated_at' => CarbonImmutable::now(),
        ];
    }

    public function periodic(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ReportType::Periodic,
            'experiment_id' => null,
        ]);
    }

    public function experimentVerdict(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ReportType::ExperimentVerdict,
            'period_start' => null,
            'period_end' => null,
            'experiment_id' => fn (array $attributes): int => Experiment::factory()
                ->create([
                    'account_id' => $attributes['account_id'],
                    'strategy_id' => $attributes['strategy_id'],
                ])
                ->id,
        ]);
    }
}
