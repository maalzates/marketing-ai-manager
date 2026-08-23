<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Services;

use App\Modules\Experiments\Domain\Enums\ExpectedResultOperator;
use App\Modules\Experiments\Domain\ValueObjects\ExpectedResult;
use App\Modules\Experiments\Domain\ValueObjects\MetricTotals;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use App\Modules\Experiments\Infrastructure\Persistence\ExperimentMetric;
use App\Modules\Reporting\Domain\Enums\AnomalyKind;
use App\Modules\Reporting\Domain\ValueObjects\AnomalyFinding;
use Illuminate\Support\Collection;

/**
 * Arithmetic, never a model. Detecting an anomaly is comparing numbers, so the daily pass
 * over every active experiment costs nothing; only the wording of a raised proposal is
 * worth a token. This class is also the whole definition of what counts as an anomaly —
 * three rules, deliberately, because a fourth marginal one is how a guardián becomes noise.
 *
 * @see AnomalyKind for which of them survive the learning phase.
 */
readonly class AnomalyDetector
{
    /**
     * @param  Collection<int, ExperimentMetric>  $daily
     * @return Collection<int, AnomalyFinding>
     */
    public function detect(Experiment $experiment, Collection $daily, float $anomalyMultiplier): Collection
    {
        return $daily->isEmpty()
            ? collect()
            : $this->rulesAgainst($experiment, MetricTotals::fromDaily($daily), $anomalyMultiplier);
    }

    /**
     * @return Collection<int, AnomalyFinding>
     */
    private function rulesAgainst(
        Experiment $experiment,
        MetricTotals $totals,
        float $anomalyMultiplier,
    ): Collection {
        return collect([
            $this->spendWithoutDelivery($experiment, $totals),
            $this->spendOverBudget($experiment, $totals),
            $this->mainMetricFarOffTarget($experiment, $totals, $anomalyMultiplier),
        ])->filter()->values();
    }

    /** Money leaving with nothing coming back — the one failure that is never volatility. */
    private function spendWithoutDelivery(Experiment $experiment, MetricTotals $totals): ?AnomalyFinding
    {
        if ($totals->spend <= 0.0 || $totals->impressions > 0) {
            return null;
        }

        return $this->finding(
            $experiment,
            AnomalyKind::SpendWithoutDelivery,
            sprintf(
                '%s lleva %s gastando %.2f sin registrar una sola impresión.',
                $experiment->code,
                self::days($totals->days),
                $totals->spend,
            ),
            ['spend' => $totals->spend, 'impressions' => 0, 'days_with_data' => $totals->days],
        );
    }

    /**
     * The cap the user set, crossed before the experiment was due to end. No tolerance and
     * no projection: a threshold that fires on a healthy experiment teaches people to ignore it.
     */
    private function spendOverBudget(Experiment $experiment, MetricTotals $totals): ?AnomalyFinding
    {
        if ($experiment->max_budget === null || $totals->spend < (float) $experiment->max_budget) {
            return null;
        }

        return $this->finding(
            $experiment,
            AnomalyKind::SpendOverBudget,
            sprintf(
                '%s ya gastó %.2f, alcanzando su tope de %.2f antes de su fecha de fin.',
                $experiment->code,
                $totals->spend,
                (float) $experiment->max_budget,
            ),
            [
                'spend' => $totals->spend,
                'max_budget' => (float) $experiment->max_budget,
                'ends_at' => $experiment->ends_at?->toDateString(),
                'days_with_data' => $totals->days,
            ],
        );
    }

    private function mainMetricFarOffTarget(
        Experiment $experiment,
        MetricTotals $totals,
        float $anomalyMultiplier,
    ): ?AnomalyFinding {
        if (! ExpectedResult::isComplete($experiment->expected_result)) {
            return null;
        }

        $expected = ExpectedResult::fromArray($experiment->expected_result);
        $actual = $totals->valueOf($expected->metric);

        if ($actual === null || ! $this->isFarOffTarget($expected, $actual, $anomalyMultiplier)) {
            return null;
        }

        return $this->finding(
            $experiment,
            AnomalyKind::MainMetricFarOffTarget,
            sprintf(
                '%s esperaba %s %s %.4f y va en %.4f, más de %.1fx peor.',
                $experiment->code,
                $expected->metric,
                $expected->operator->label(),
                $expected->value,
                $actual,
                $anomalyMultiplier,
            ),
            [
                'metric' => $expected->metric,
                'operator' => $expected->operator->value,
                'expected_value' => $expected->value,
                'actual_value' => $actual,
                'anomaly_multiplier' => $anomalyMultiplier,
                'days_with_data' => $totals->days,
            ],
        );
    }

    /**
     * "Worse" runs in the direction the expectation points: a cost metric is worse when it
     * multiplies, a rate metric is worse when it divides. Same multiplier, mirrored.
     */
    private function isFarOffTarget(ExpectedResult $expected, float $actual, float $anomalyMultiplier): bool
    {
        return $anomalyMultiplier > 0.0 && ! $expected->operator->isSatisfiedBy(
            $actual,
            $expected->operator === ExpectedResultOperator::Lte
                ? $expected->value * $anomalyMultiplier
                : $expected->value / $anomalyMultiplier,
        );
    }

    private static function days(int $days): string
    {
        return $days === 1 ? '1 día' : sprintf('%d días', $days);
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function finding(
        Experiment $experiment,
        AnomalyKind $kind,
        string $summary,
        array $evidence,
    ): AnomalyFinding {
        return new AnomalyFinding($kind, (int) $experiment->id, (string) $experiment->code, $summary, $evidence);
    }
}
