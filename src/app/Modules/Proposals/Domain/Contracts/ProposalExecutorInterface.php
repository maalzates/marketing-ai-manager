<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Domain\Contracts;

use App\Modules\Proposals\Domain\ValueObjects\ExecutionOutcome;
use App\Modules\Proposals\Infrastructure\Persistence\Proposal;

interface ProposalExecutorInterface
{
    /**
     * Performs the real mutation, or validates it and hands it to the queue, and says which
     * of the two it did.
     */
    public function execute(Proposal $proposal): ExecutionOutcome;
}
