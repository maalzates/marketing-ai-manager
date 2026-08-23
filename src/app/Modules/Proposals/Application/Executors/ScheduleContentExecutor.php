<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Application\Executors;

use App\Modules\Proposals\Domain\Contracts\ProposalExecutorInterface;
use App\Modules\Proposals\Domain\Exceptions\ProposalExecutorNotAvailableException;
use App\Modules\Proposals\Domain\ValueObjects\ExecutionOutcome;
use App\Modules\Proposals\Infrastructure\Persistence\Proposal;

/** Waits on the Content module: scheduling means handing the piece to a channel provider. */
readonly class ScheduleContentExecutor implements ProposalExecutorInterface
{
    public function execute(Proposal $proposal): ExecutionOutcome
    {
        throw ProposalExecutorNotAvailableException::forType($proposal->type);
    }
}
