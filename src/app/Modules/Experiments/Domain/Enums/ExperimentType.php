<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Domain\Enums;

enum ExperimentType: string
{
    case Organic = 'organic';
    case Ads = 'ads';
}
