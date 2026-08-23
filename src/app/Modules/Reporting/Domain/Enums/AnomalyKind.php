<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain\Enums;

/**
 * The complete list of things the guardián is allowed to notice. Anything not here produces
 * silence, which is the point: a guardián that reports every day is a guardián nobody reads.
 */
enum AnomalyKind: string
{
    case SpendWithoutDelivery = 'spend_without_delivery';
    case SpendOverBudget = 'spend_over_budget';
    case MainMetricFarOffTarget = 'main_metric_far_off_target';

    /** Short enough for a proposal title; the numbers behind it live in the finding's evidence. */
    public function label(): string
    {
        return match ($this) {
            self::SpendWithoutDelivery => 'gasto sin entrega',
            self::SpendOverBudget => 'presupuesto agotado antes de tiempo',
            self::MainMetricFarOffTarget => 'métrica principal muy lejos de lo esperado',
        };
    }

    /**
     * Meta's learning phase makes early cost and performance volatile by design, so judging
     * them is judging noise (core.md §10.6). Money leaving with nothing coming back is not
     * volatility, and stays actionable from day one.
     */
    public function isEvidentDisaster(): bool
    {
        return match ($this) {
            self::SpendWithoutDelivery, self::SpendOverBudget => true,
            self::MainMetricFarOffTarget => false,
        };
    }
}
