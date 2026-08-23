<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Domain\Enums;

enum WarningCode: string
{
    case LearningPhaseWindow = 'learning_phase_window';
    case MinimumDailyBudget = 'minimum_daily_budget';
    case MinimumEvaluationDate = 'minimum_evaluation_date';
    case EditsResetLearning = 'edits_reset_learning';
    case DomainRuleUnavailable = 'domain_rule_unavailable';
}
