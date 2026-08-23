<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Experiments\Domain\Enums\WarningCode;
use App\Modules\Experiments\Domain\Enums\WarningSeverity;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use App\Modules\Experiments\Infrastructure\Persistence\ExperimentWarning;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExperimentWarning>
 */
class ExperimentWarningFactory extends Factory
{
    protected $model = ExperimentWarning::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'experiment_id' => Experiment::factory(),
            'code' => WarningCode::LearningPhaseWindow->value,
            'message' => fake()->sentence(12),
            'severity' => WarningSeverity::Info,
            'applies_from' => CarbonImmutable::now()->startOfDay(),
            'applies_to' => CarbonImmutable::now()->startOfDay()->addDays(7),
        ];
    }

    public function critical(): self
    {
        return $this->state(fn (): array => [
            'code' => WarningCode::MinimumDailyBudget->value,
            'severity' => WarningSeverity::Critical,
        ]);
    }
}
