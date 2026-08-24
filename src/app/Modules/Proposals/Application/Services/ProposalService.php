<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Application\Services;

use App\Modules\Audit\Application\DTO\RecordActionDTO;
use App\Modules\Audit\Application\Services\ActionLogService;
use App\Modules\Audit\Domain\Enums\ActionOrigin;
use App\Modules\Experiments\Application\Services\ExperimentService;
use App\Modules\Proposals\Application\DTO\ProposalFilterDTO;
use App\Modules\Proposals\Application\DTO\ProposeDTO;
use App\Modules\Proposals\Domain\Contracts\ProposalRepositoryInterface;
use App\Modules\Proposals\Domain\Exceptions\ProposalAlreadyDecidedException;
use App\Modules\Proposals\Domain\Exceptions\ProposalExpiredException;
use App\Modules\Proposals\Domain\Exceptions\ProposalNotFoundException;
use App\Modules\Proposals\Infrastructure\Persistence\Proposal;
use App\Modules\Strategies\Application\Services\StrategyService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * The only class a chat tool or a job is allowed to touch. It writes a Proposal and
 * returns it; nothing here executes anything. Execution lives in ProposalExecutionService,
 * which the container hands out only to the human-approval controller.
 */
readonly class ProposalService
{
    private const string ENTITY_TYPE = 'proposal';

    public function __construct(
        private ProposalRepositoryInterface $repository,
        private ExperimentService $experiments,
        private StrategyService $strategies,
        private ActionLogService $actionLog,
    ) {}

    public function propose(ProposeDTO $dto): Proposal
    {
        if ($dto->strategyId !== null) {
            $this->strategies->find($dto->strategyId, $dto->accountId);
        }

        if ($dto->experimentId !== null) {
            $this->experiments->find($dto->experimentId, $dto->accountId);
        }

        return $this->repository->create($dto);
    }

    /**
     * @return Collection<int, Proposal>|LengthAwarePaginator<int, Proposal>
     */
    public function list(ProposalFilterDTO $filters): Collection|LengthAwarePaginator
    {
        return $this->repository->findAll($filters);
    }

    public function find(int $id, int $accountId): Proposal
    {
        return $this->repository->findById($id, $accountId) ?? throw ProposalNotFoundException::withId($id);
    }

    public function reject(int $id, int $accountId, int $userId, ?string $reason = null): Proposal
    {
        $proposal = $this->assertDecidable($this->find($id, $accountId));

        $this->actionLog->record(new RecordActionDTO(
            $accountId,
            $userId,
            'proposal.rejected',
            ActionOrigin::UI,
            ['type' => $proposal->type->value, 'title' => $proposal->title, 'reason' => $reason],
            self::ENTITY_TYPE,
            $id,
        ));

        return $this->repository->markRejected($proposal, $userId, $reason);
    }

    public function expire(int $id, int $accountId): Proposal
    {
        return $this->repository->markExpired($this->find($id, $accountId));
    }

    /** A decision is only valid on a pending, unexpired proposal — a double click must not run twice. */
    public function assertDecidable(Proposal $proposal): Proposal
    {
        if (! $proposal->isPending()) {
            throw ProposalAlreadyDecidedException::withStatus((int) $proposal->id, $proposal->status);
        }

        if ($proposal->hasExpired()) {
            $this->repository->markExpired($proposal);

            throw ProposalExpiredException::at((int) $proposal->id, $proposal->expires_at);
        }

        return $proposal;
    }
}
