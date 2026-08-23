<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Domain\Enums;

enum ProposalStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    /** Validated and handed to the queue; the platform has not confirmed anything yet. */
    case Executing = 'executing';

    case Executed = 'executed';
    case Failed = 'failed';
    case Expired = 'expired';
}
