<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Domain\Enums;

enum ProposalType: string
{
    case CreateCampaign = 'create_campaign';
    case BudgetChange = 'budget_change';
    case PauseExperiment = 'pause_experiment';
    case CloseExperiment = 'close_experiment';
    case ScheduleContent = 'schedule_content';
}
