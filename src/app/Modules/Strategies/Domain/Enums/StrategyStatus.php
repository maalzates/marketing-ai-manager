<?php

declare(strict_types=1);

namespace App\Modules\Strategies\Domain\Enums;

enum StrategyStatus: string
{
    case Active = 'active';

    case Paused = 'paused';

    case Archived = 'archived';
}
