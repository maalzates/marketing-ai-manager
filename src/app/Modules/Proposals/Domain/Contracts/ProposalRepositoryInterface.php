<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Domain\Contracts;

use App\Modules\Proposals\Application\DTO\ProposalFilterDTO;
use App\Modules\Proposals\Application\DTO\ProposeDTO;
use App\Modules\Proposals\Infrastructure\Persistence\Proposal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProposalRepositoryInterface
{
    /**
     * @return Collection<int, Proposal>|LengthAwarePaginator<int, Proposal>
     */
    public function findAll(ProposalFilterDTO $filters): Collection|LengthAwarePaginator;

    public function findById(int $id, int $accountId): ?Proposal;

    public function create(ProposeDTO $dto): Proposal;

    public function markAccepted(Proposal $proposal, int $userId): Proposal;

    public function markRejected(Proposal $proposal, int $userId, ?string $reason): Proposal;

    public function markExpired(Proposal $proposal): Proposal;

    /**
     * @param  array<string, mixed>  $executionResult
     */
    public function markExecuting(Proposal $proposal, array $executionResult): Proposal;

    /**
     * @param  array<string, mixed>  $executionResult
     */
    public function markExecuted(Proposal $proposal, array $executionResult): Proposal;

    public function markFailed(Proposal $proposal, string $reason): Proposal;
}
