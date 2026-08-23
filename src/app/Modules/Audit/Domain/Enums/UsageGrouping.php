<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain\Enums;

enum UsageGrouping: string
{
    case DAY = 'day';
    case FEATURE = 'feature';
    case ACCOUNT = 'account';
}
