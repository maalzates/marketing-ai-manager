<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Application\Executors;

use App\Modules\Audit\Domain\Enums\ActionOrigin;
use App\Modules\Experiments\Application\Services\VerdictService;
use App\Modules\Experiments\Domain\Enums\Verdict;
use App\Modules\Proposals\Domain\Contracts\ProposalExecutorInterface;
use App\Modules\Proposals\Domain\Exceptions\ProposalPayloadInvalidException;
use App\Modules\Proposals\Domain\ValueObjects\ExecutionOutcome;
use App\Modules\Proposals\Infrastructure\Persistence\Proposal;
use Carbon\CarbonImmutable;

/**
 * The guardián's early-close flow: accepting the proposal is what writes the verdict and
 * puts the experiment in the history the assistant learns from.
 */
readonly class CloseExperimentExecutor implements ProposalExecutorInterface
{
    public function __construct(private VerdictService $verdicts) {}

    public function execute(Proposal $proposal): ExecutionOutcome
    {
        return ExecutionOutcome::completed([
            'experiment_id' => (int) $this->verdicts->confirm(
                $this->experimentId($proposal),
                (int) $proposal->account_id,
                $this->verdict($proposal),
                (string) ($proposal->payload['reason'] ?? $proposal->rationale),
                $proposal->decided_by_user_id === null ? null : (int) $proposal->decided_by_user_id,
                ActionOrigin::UI,
            )->id,
            'verdict' => $this->verdict($proposal)->value,
            'closed_at' => CarbonImmutable::now()->toIso8601String(),
        ]);
    }

    private function experimentId(Proposal $proposal): int
    {
        return $proposal->experiment_id === null
            ? throw ProposalPayloadInvalidException::missing($proposal->type, 'experiment_id')
            : (int) $proposal->experiment_id;
    }

    private function verdict(Proposal $proposal): Verdict
    {
        return Verdict::tryFrom((string) ($proposal->payload['verdict'] ?? ''))
            ?? throw ProposalPayloadInvalidException::missing($proposal->type, 'payload.verdict');
    }
}
