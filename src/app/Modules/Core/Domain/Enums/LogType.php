<?php

declare(strict_types=1);

namespace App\Modules\Core\Domain\Enums;

enum LogType: string
{
    case ERROR = 'error';
    case WARNING = 'warning';
    case INFO = 'info';
    case DEBUG = 'debug';
}
