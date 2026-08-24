<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Services;

use App\Modules\Campaigns\Domain\Exceptions\CampaignBudgetExceedsCapException;
use App\Modules\Campaigns\Domain\ValueObjects\BudgetPlan;
use App\Modules\Experiments\Application\Services\MetaAdsRuleService;
use App\Modules\Experiments\Domain\ValueObjects\ExpectedResult;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use App\Modules\Settings\Application\Services\SettingsService;

/**
 * The budget ceiling, in backend code. Meta's own limits are a second line of defence and
 * the LLM is none at all: whatever a proposal asks for, the amount is checked here before
 * a single euro reaches the platform.
 */
readonly class CampaignBudgetGuard
{
    private const string MAX_BUDGET_SETTING = 'budgets.max_per_experiment';

    public function __construct(
        private SettingsService $settings,
        private MetaAdsRuleService $rules,
    ) {}

    /**
     * The strategy's remaining budget is already spoken for: the experiment reserved it
     * against the strategy when it was created, so staying inside `max_budget` is what
     * keeps the campaign inside the strategy.
     *
     * @throws CampaignBudgetExceedsCapException
     */
    public function assertWithinCaps(Experiment $experiment, BudgetPlan $budget): void
    {
        $requested = $budget->total(self::durationDays($experiment));

        if ($requested === null) {
            return;
        }

        $cap = (float) $this->settings->get(self::MAX_BUDGET_SETTING, (int) $experiment->account_id);

        if ($requested > $cap) {
            throw CampaignBudgetExceedsCapException::overAccountCap($requested, $cap);
        }

        if ($experiment->max_budget !== null && $requested > (float) $experiment->max_budget) {
            throw CampaignBudgetExceedsCapException::overExperimentBudget(
                $requested,
                (float) $experiment->max_budget,
                (int) $experiment->id,
            );
        }
    }

    /**
     * Below the mathematical minimum the ad set never leaves the learning phase, which is a
     * reason to warn loudly and let the human decide — not a reason to block the launch.
     *
     * @return list<string>
     */
    public function warningsFor(Experiment $experiment, BudgetPlan $budget): array
    {
        $expected = ExpectedResult::fromArray($experiment->expected_result);
        $daily = self::dailyBudget($experiment, $budget);

        if (! $expected->isCostPerAction() || $daily === null) {
            return [];
        }

        return $daily >= $this->rules->minimumDailyBudgetFor($expected->value)
            ? []
            : [$this->underfundedMessage($expected, $daily)];
    }

    private function underfundedMessage(ExpectedResult $expected, float $daily): string
    {
        return sprintf(
            'Con un %s objetivo de %s, el presupuesto diario mínimo matemático es %s — (%s × %d) ÷ %d. Esta '
            .'campaña da %s al día, por debajo de ese mínimo: el ad set se queda sin salir de la fase de '
            .'aprendizaje. Sube el presupuesto u optimiza por un evento del funnel más frecuente.',
            strtoupper($expected->metric),
            number_format($expected->value, 2),
            number_format($this->rules->minimumDailyBudgetFor($expected->value), 2),
            number_format($expected->value, 2),
            // The formula pair, not the learning pair: these two numbers are printed as the
            // arithmetic behind minimumDailyBudgetFor(), so they have to be the ones it used.
            $this->rules->budgetFormulaEvents(),
            $this->rules->budgetFormulaWindowDays(),
            number_format($daily, 2),
        );
    }

    private static function dailyBudget(Experiment $experiment, BudgetPlan $budget): ?float
    {
        return $budget->daily ?? match ($budget->lifetime) {
            null => null,
            default => $budget->lifetime / self::durationDays($experiment),
        };
    }

    private static function durationDays(Experiment $experiment): int
    {
        return max(1, (int) $experiment->starts_at->diffInDays($experiment->ends_at));
    }
}
