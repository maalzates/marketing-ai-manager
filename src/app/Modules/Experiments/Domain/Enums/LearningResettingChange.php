<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Domain\Enums;

/**
 * The edits Meta restarts the learning phase for, regardless of magnitude. Budget is not
 * here: it only resets past a configured percentage, so it is decided numerically.
 */
enum LearningResettingChange: string
{
    case Targeting = 'targeting';
    case Creative = 'creative';
    case Bid = 'bid';
}
