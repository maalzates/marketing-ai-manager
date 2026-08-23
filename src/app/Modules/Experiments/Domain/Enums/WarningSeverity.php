<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Domain\Enums;

enum WarningSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';
}
