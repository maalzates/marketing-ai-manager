<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Domain\Contracts;

/**
 * How a queued mutation closes out the proposal that authorised it.
 *
 * This is the only way to move a proposal off `executing`, and it records an outcome that
 * already happened — it can start nothing. Handing it to a job is therefore safe in a way
 * that handing out ProposalExecutionService is not.
 */
interface ProposalOutcomeRecorderInterface
{
    /**
     * @param  array<string, mixed>  $result
     */
    public function recordSuccess(int $proposalId, int $accountId, array $result): void;

    public function recordFailure(int $proposalId, int $accountId, string $reason): void;
}
