<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain\Enums;

enum ActionOrigin: string
{
    case UI = 'ui';
    case CHAT = 'chat';
    case JOB = 'job';
    case API = 'api';
}
