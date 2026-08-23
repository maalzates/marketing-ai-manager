<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Enums;

enum ScheduleStatus: string
{
    case Pending = 'pending';
    case Publishing = 'publishing';
    case Published = 'published';
    case Failed = 'failed';
}
