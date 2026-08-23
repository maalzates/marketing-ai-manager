<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Application\Services;

use App\Modules\Experiments\Domain\Enums\LearningResettingChange;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

readonly class LearningPhaseService
{
    /**
     * The change keys whose magnitude decides the reset, as opposed to the ones that reset
     * unconditionally (LearningResettingChange).
     *
     * @var list<string>
     */
    private const array BUDGET_CHANGE_FIELDS = ['max_budget', 'daily_budget', 'lifetime_budget'];

    public function __construct(private MetaAdsRuleService $rules) {}

    /**
     * @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}
     */
    public function windowFor(Experiment $experiment): array
    {
        return [
            'starts_at' => $experiment->starts_at,
            'ends_at' => $experiment->learning_phase_ends_at ?? $this->endsAt($experiment->starts_at),
        ];
    }

    public function endsAt(CarbonImmutable $startsAt): CarbonImmutable
    {
        return $startsAt->addDays($this->rules->learningWindowDays());
    }

    public function isWithinLearningWindow(Experiment $experiment, CarbonInterface $at): bool
    {
        return $at->greaterThanOrEqualTo($this->windowFor($experiment)['starts_at'])
            && $at->lessThan($this->windowFor($experiment)['ends_at']);
    }

    /**
     * @param  array<string, array{from: mixed, to: mixed}>  $changes
     */
    public function resetsLearning(array $changes): bool
    {
        return $this->touchesResettingField($changes)
            || $this->largestBudgetChangePercent($changes) > $this->rules->significantBudgetChangePercent();
    }

    /**
     * @param  array<string, array{from: mixed, to: mixed}>  $changes
     */
    private function touchesResettingField(array $changes): bool
    {
        return collect(LearningResettingChange::cases())
            ->contains(fn (LearningResettingChange $field): bool => array_key_exists($field->value, $changes));
    }

    /**
     * @param  array<string, array{from: mixed, to: mixed}>  $changes
     */
    private function largestBudgetChangePercent(array $changes): float
    {
        return (float) collect(self::BUDGET_CHANGE_FIELDS)
            ->map(fn (string $field): float => $this->percentChange($changes[$field] ?? null))
            ->max();
    }

    private function percentChange(?array $change): float
    {
        return is_numeric($change['from'] ?? null) && is_numeric($change['to'] ?? null) && (float) $change['from'] > 0.0
            ? abs((float) $change['to'] - (float) $change['from']) / (float) $change['from'] * 100
            : 0.0;
    }
}
