<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Infrastructure\Repositories;

use App\Modules\Proposals\Application\DTO\ProposalFilterDTO;
use App\Modules\Proposals\Application\DTO\ProposeDTO;
use App\Modules\Proposals\Domain\Contracts\ProposalRepositoryInterface;
use App\Modules\Proposals\Domain\Enums\ProposalOrigin;
use App\Modules\Proposals\Domain\Enums\ProposalStatus;
use App\Modules\Proposals\Domain\Enums\ProposalType;
use App\Modules\Proposals\Domain\Exceptions\ProposalPersistenceFailedException;
use App\Modules\Proposals\Infrastructure\Persistence\Proposal;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

readonly class ProposalRepository implements ProposalRepositoryInterface
{
    public function __construct(private Proposal $model) {}

    public function findAll(ProposalFilterDTO $filters): Collection|LengthAwarePaginator
    {
        return $filters->perPage > 0
            ? $this->query($filters)->paginate(perPage: $filters->perPage, page: $filters->page)
            : $this->query($filters)->get();
    }

    public function findById(int $id, int $accountId): ?Proposal
    {
        return $this->model->newQuery()->where('account_id', $accountId)->find($id);
    }

    public function create(ProposeDTO $dto): Proposal
    {
        try {
            return $this->model->newQuery()->create([
                'account_id' => $dto->accountId,
                'user_id' => $dto->userId,
                'strategy_id' => $dto->strategyId,
                'experiment_id' => $dto->experimentId,
                'type' => $dto->type,
                'title' => $dto->title,
                'rationale' => $dto->rationale,
                'payload' => $dto->payload,
                'status' => ProposalStatus::Pending,
                'origin' => $dto->origin,
                'expires_at' => $dto->expiresAt,
            ]);
        } catch (Throwable $exception) {
            throw ProposalPersistenceFailedException::wrap($exception, context: [
                'account_id' => $dto->accountId,
                'type' => $dto->type->value,
            ]);
        }
    }

    public function markAccepted(Proposal $proposal, int $userId): Proposal
    {
        return $this->transitionTo($proposal, [
            'status' => ProposalStatus::Accepted,
            'decided_at' => CarbonImmutable::now(),
            'decided_by_user_id' => $userId,
        ]);
    }

    public function markRejected(Proposal $proposal, int $userId, ?string $reason): Proposal
    {
        return $this->transitionTo($proposal, [
            'status' => ProposalStatus::Rejected,
            'decided_at' => CarbonImmutable::now(),
            'decided_by_user_id' => $userId,
            'execution_result' => ['reason' => $reason],
        ]);
    }

    public function markExpired(Proposal $proposal): Proposal
    {
        return $this->transitionTo($proposal, ['status' => ProposalStatus::Expired]);
    }

    public function markExecuting(Proposal $proposal, array $executionResult): Proposal
    {
        return $this->transitionTo($proposal, [
            'status' => ProposalStatus::Executing,
            'execution_result' => $executionResult,
        ]);
    }

    public function markExecuted(Proposal $proposal, array $executionResult): Proposal
    {
        return $this->transitionTo($proposal, [
            'status' => ProposalStatus::Executed,
            'execution_result' => $executionResult,
        ]);
    }

    public function markFailed(Proposal $proposal, string $reason): Proposal
    {
        return $this->transitionTo($proposal, [
            'status' => ProposalStatus::Failed,
            'execution_result' => ['reason' => $reason],
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function transitionTo(Proposal $proposal, array $attributes): Proposal
    {
        try {
            $proposal->update($attributes);

            return $proposal->refresh();
        } catch (Throwable $exception) {
            throw ProposalPersistenceFailedException::wrap($exception, context: [
                'proposal_id' => $proposal->id,
                'status' => $attributes['status'] instanceof ProposalStatus ? $attributes['status']->value : null,
            ]);
        }
    }

    private function query(ProposalFilterDTO $filters): Builder
    {
        return $this->model->newQuery()
            ->where('account_id', $filters->accountId)
            ->when($filters->status, fn (Builder $query, ProposalStatus $status) => $query->where('status', $status))
            ->when($filters->type, fn (Builder $query, ProposalType $type) => $query->where('type', $type))
            ->when($filters->origin, fn (Builder $query, ProposalOrigin $origin) => $query->where('origin', $origin))
            ->when($filters->strategyId, fn (Builder $query, int $id) => $query->where('strategy_id', $id))
            ->when($filters->experimentId, fn (Builder $query, int $id) => $query->where('experiment_id', $id))
            ->orderByDesc('id');
    }
}
