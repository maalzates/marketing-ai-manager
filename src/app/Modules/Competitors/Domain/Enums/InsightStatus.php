<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Domain\Enums;

enum InsightStatus: string
{
    case New = 'new';
    case Used = 'used';
    case Discarded = 'discarded';
}
