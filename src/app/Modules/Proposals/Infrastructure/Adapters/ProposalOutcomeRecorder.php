<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Infrastructure\Adapters;

use App\Modules\Proposals\Domain\Contracts\ProposalOutcomeRecorderInterface;
use App\Modules\Proposals\Domain\Contracts\ProposalRepositoryInterface;
use App\Modules\Proposals\Domain\Enums\ProposalStatus;
use App\Modules\Proposals\Infrastructure\Persistence\Proposal;

/**
 * Depends on the repository rather than on ProposalService so a queued mutation can close
 * out its own proposal without pulling the module's decision logic into a job.
 *
 * Only a proposal still on `executing` moves: a job reporting twice, or reporting on a
 * proposal a human already resolved, must not overwrite the record.
 */
readonly class ProposalOutcomeRecorder implements ProposalOutcomeRecorderInterface
{
    public function __construct(private ProposalRepositoryInterface $repository) {}

    public function recordSuccess(int $proposalId, int $accountId, array $result): void
    {
        $this->whenExecuting(
            $proposalId,
            $accountId,
            fn (Proposal $proposal) => $this->repository->markExecuted($proposal, $result),
        );
    }

    public function recordFailure(int $proposalId, int $accountId, string $reason): void
    {
        $this->whenExecuting(
            $proposalId,
            $accountId,
            fn (Proposal $proposal) => $this->repository->markFailed($proposal, $reason),
        );
    }

    private function whenExecuting(int $proposalId, int $accountId, callable $transition): void
    {
        $proposal = $this->repository->findById($proposalId, $accountId);

        if ($proposal?->status === ProposalStatus::Executing) {
            $transition($proposal);
        }
    }
}
