<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Domain\Enums;

/**
 * `learning_stage_info.status` as the API documents it. Ads Manager renders FAIL as
 * "Learning limited"; that label is a UI string and never a value we store or match on.
 */
enum LearningStage: string
{
    case Learning = 'LEARNING';
    case Success = 'SUCCESS';
    case Fail = 'FAIL';
}
