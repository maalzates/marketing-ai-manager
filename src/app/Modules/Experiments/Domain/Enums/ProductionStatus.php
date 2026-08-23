<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Domain\Enums;

enum ProductionStatus: string
{
    case Script = 'script';
    case Recorded = 'recorded';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Failed = 'failed';
}
