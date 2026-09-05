<?php

declare(strict_types=1);

namespace App\Modules\Strategies\Domain\Enums;

enum NorthStarMetric: string
{
    case Conversions = 'conversions';

    case Roas = 'roas';

    case Cpa = 'cpa';

    case Cpl = 'cpl';

    case CostPerFollower = 'cost_per_follower';
}
