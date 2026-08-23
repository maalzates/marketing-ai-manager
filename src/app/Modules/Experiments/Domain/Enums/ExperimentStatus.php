<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Domain\Enums;

enum ExperimentStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Running = 'running';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** Budget already promised to the strategy: what a closed experiment spent no longer competes for it. */
    public function commitsBudget(): bool
    {
        return in_array($this, [self::Draft, self::Scheduled, self::Running], true);
    }
}
