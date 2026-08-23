<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Application\Executors;

use App\Modules\Proposals\Domain\Contracts\ProposalExecutorInterface;
use App\Modules\Proposals\Domain\Exceptions\ProposalExecutorNotAvailableException;
use App\Modules\Proposals\Domain\ValueObjects\ExecutionOutcome;
use App\Modules\Proposals\Infrastructure\Persistence\Proposal;

/** Waits on the Campaigns module: the new budget has to be written to the ad set in Meta. */
readonly class BudgetChangeExecutor implements ProposalExecutorInterface
{
    public function execute(Proposal $proposal): ExecutionOutcome
    {
        throw ProposalExecutorNotAvailableException::forType($proposal->type);
    }
}
