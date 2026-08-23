<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Application\Services;

use App\Modules\Audit\Application\DTO\RecordActionDTO;
use App\Modules\Audit\Application\Services\ActionLogService;
use App\Modules\Audit\Domain\Enums\ActionOrigin;
use App\Modules\Core\Domain\Exceptions\ClientException;
use App\Modules\Proposals\Application\Executors\ProposalExecutorRegistry;
use App\Modules\Proposals\Domain\Contracts\ProposalRepositoryInterface;
use App\Modules\Proposals\Domain\ValueObjects\ExecutionOutcome;
use App\Modules\Proposals\Infrastructure\Persistence\Proposal;
use Throwable;

/**
 * The only class in the application that turns a Proposal into a real mutation. The
 * container refuses to resolve it for anything but AcceptProposalController — see
 * ProposalsServiceProvider — so there is no code path from a chat tool or a job to here.
 */
final readonly class ProposalExecutionService
{
    private const string ENTITY_TYPE = 'proposal';

    public function __construct(
        private ProposalService $proposals,
        private ProposalRepositoryInterface $repository,
        private ProposalExecutorRegistry $executors,
        private ActionLogService $actionLog,
    ) {}

    public function accept(int $proposalId, int $accountId, int $userId): Proposal
    {
        $proposal = $this->proposals->assertDecidable($this->proposals->find($proposalId, $accountId));

        $this->actionLog->record(new RecordActionDTO(
            $accountId,
            $userId,
            'proposal.accepted',
            ActionOrigin::UI,
            ['type' => $proposal->type->value, 'title' => $proposal->title],
            self::ENTITY_TYPE,
            $proposalId,
        ));

        return $this->execute($this->repository->markAccepted($proposal, $userId));
    }

    private function execute(Proposal $proposal): Proposal
    {
        try {
            return $this->record($proposal, $this->executors->resolve($proposal->type)->execute($proposal));
        } catch (Throwable $exception) {
            // The one catch outside a repository, and it swallows nothing. An accepted
            // proposal whose execution blew up has to land on `failed` with the reason,
            // or the UI keeps offering the button and the next click runs the mutation a
            // second time. The original exception is rethrown untouched so the handler
            // still logs it exactly once, at the level the exception itself decides.
            $this->repository->markFailed($proposal, $this->reasonFrom($exception));

            throw $exception;
        }
    }

    /** `executed` claims the platform confirmed it; queued work has only been handed over. */
    private function record(Proposal $proposal, ExecutionOutcome $outcome): Proposal
    {
        return $outcome->isDeferred
            ? $this->repository->markExecuting($proposal, $outcome->result)
            : $this->repository->markExecuted($proposal, $outcome->result);
    }

    private function reasonFrom(Throwable $exception): string
    {
        return $exception instanceof ClientException
            ? $exception->getClientMessage()
            : 'La ejecución de la propuesta falló por un error interno.';
    }
}
