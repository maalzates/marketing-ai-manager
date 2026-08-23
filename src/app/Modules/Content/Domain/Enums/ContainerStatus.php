<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Enums;

/**
 * `status_code` of an Instagram publishing container. Modelled as a domain enum because the
 * publish flow is a state machine over these five values, and an unknown sixth value must
 * fail loudly rather than be read as "keep waiting".
 */
enum ContainerStatus: string
{
    case InProgress = 'IN_PROGRESS';
    case Finished = 'FINISHED';
    case Published = 'PUBLISHED';
    case Error = 'ERROR';
    case Expired = 'EXPIRED';

    public function isTerminalFailure(): bool
    {
        return $this === self::Error || $this === self::Expired || $this === self::Published;
    }
}
