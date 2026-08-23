<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Domain\Enums;

/**
 * Keys of the `domain_rule` knowledge entries this module reads its numbers from. Meta
 * changes them and an admin edits the entry — nothing here is a literal in PHP.
 */
enum MetaAdsRule: string
{
    case LearningPhase = 'meta-ads-01-learning-phase';
    case EditsResetLearning = 'meta-ads-02-edits-reset-learning';
    case MinimumDailyBudget = 'meta-ads-03-minimum-budget';
    case MinimumExperimentDuration = 'meta-ads-06-minimum-duration';

    /**
     * The numeric metadata this module reads off the entry. Anything else the entry
     * carries is prose for the LLM and the UI, and its absence changes no behaviour.
     *
     * @return list<string>
     */
    public function requiredFields(): array
    {
        return match ($this) {
            self::LearningPhase => ['events_needed', 'window_days'],
            self::EditsResetLearning => ['significant_budget_change_percent'],
            self::MinimumDailyBudget => ['events_needed', 'window_days'],
            self::MinimumExperimentDuration => ['minimum_duration_days'],
        };
    }
}
