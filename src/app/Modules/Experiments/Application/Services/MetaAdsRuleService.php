<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Application\Services;

use App\Modules\Experiments\Domain\Enums\MetaAdsRule;
use App\Modules\Knowledge\Application\Services\KnowledgeService;
use App\Modules\Knowledge\Domain\Enums\KnowledgeType;
use App\Modules\Knowledge\Infrastructure\Persistence\KnowledgeEntry;
use Illuminate\Support\Collection;

/**
 * Every Meta number this module reasons with comes from the knowledge base on the happy
 * path, so an admin can follow Meta without a deploy.
 *
 * When the entry that owns a number is unpublished or has lost the field, the value below
 * is used instead and `unavailableRules()` reports it, so the experiment carries a warning
 * saying its figures are defaults. A user creating an experiment must never be blocked by
 * an admin's content edit — but they must never be shown a spending threshold that looks
 * current when it is not.
 */
readonly class MetaAdsRuleService
{
    /** meta-ads-01-learning-phase.events_needed — core.md §11.1 */
    private const int DEFAULT_LEARNING_EVENTS_NEEDED = 50;

    /** meta-ads-01-learning-phase.window_days — core.md §11.1 */
    private const int DEFAULT_LEARNING_WINDOW_DAYS = 7;

    /** meta-ads-03-minimum-budget.events_needed — core.md §11.3 */
    private const int DEFAULT_BUDGET_FORMULA_EVENTS = 50;

    /** meta-ads-03-minimum-budget.window_days — core.md §11.3 */
    private const int DEFAULT_BUDGET_FORMULA_WINDOW_DAYS = 7;

    /** meta-ads-06-minimum-duration.minimum_duration_days — core.md §11.6 */
    private const int DEFAULT_MINIMUM_DURATION_DAYS = 7;

    /** meta-ads-02-edits-reset-learning.significant_budget_change_percent — core.md §11.2 */
    private const float DEFAULT_SIGNIFICANT_BUDGET_CHANGE_PERCENT = 20.0;

    public function __construct(private KnowledgeService $knowledge) {}

    public function learningEventsNeeded(): int
    {
        return (int) $this->number(MetaAdsRule::LearningPhase, 'events_needed', self::DEFAULT_LEARNING_EVENTS_NEEDED);
    }

    public function learningWindowDays(): int
    {
        return (int) $this->number(MetaAdsRule::LearningPhase, 'window_days', self::DEFAULT_LEARNING_WINDOW_DAYS);
    }

    public function budgetFormulaEvents(): int
    {
        return (int) $this->number(MetaAdsRule::MinimumDailyBudget, 'events_needed', self::DEFAULT_BUDGET_FORMULA_EVENTS);
    }

    public function budgetFormulaWindowDays(): int
    {
        return (int) $this->number(MetaAdsRule::MinimumDailyBudget, 'window_days', self::DEFAULT_BUDGET_FORMULA_WINDOW_DAYS);
    }

    public function minimumDurationDays(): int
    {
        return (int) $this->number(MetaAdsRule::MinimumExperimentDuration, 'minimum_duration_days', self::DEFAULT_MINIMUM_DURATION_DAYS);
    }

    public function significantBudgetChangePercent(): float
    {
        return $this->number(
            MetaAdsRule::EditsResetLearning,
            'significant_budget_change_percent',
            self::DEFAULT_SIGNIFICANT_BUDGET_CHANGE_PERCENT,
        );
    }

    /** (CPA objetivo × eventos necesarios) ÷ días de la ventana. */
    public function minimumDailyBudgetFor(float $targetCostPerAction): float
    {
        return $targetCostPerAction * $this->budgetFormulaEvents() / $this->budgetFormulaWindowDays();
    }

    /**
     * The rules whose live numbers could not be read, and whose defaults are therefore in
     * play right now.
     *
     * @return Collection<int, MetaAdsRule>
     */
    public function unavailableRules(): Collection
    {
        return collect(MetaAdsRule::cases())
            ->reject(fn (MetaAdsRule $rule): bool => collect($rule->requiredFields())->every(
                fn (string $field): bool => is_numeric($this->metadataValue($this->published(), $rule, $field)),
            ))
            ->values();
    }

    /** A whole number in a JSON column decodes as int; currency arithmetic must not inherit that. */
    private function number(MetaAdsRule $rule, string $field, float|int $default): float
    {
        $value = $this->metadataValue($this->published(), $rule, $field);

        return is_numeric($value) ? (float) $value : (float) $default;
    }

    /**
     * @param  Collection<string, KnowledgeEntry>  $published
     */
    private function metadataValue(Collection $published, MetaAdsRule $rule, string $field): mixed
    {
        return ($published->get($rule->value)?->metadata ?? [])[$field] ?? null;
    }

    /**
     * @return Collection<string, KnowledgeEntry>
     */
    private function published(): Collection
    {
        return $this->knowledge->listByType(KnowledgeType::DomainRule)->keyBy('key');
    }
}
