<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Infrastructure\Repositories;

use App\Modules\Experiments\Application\DTO\CreateExperimentDTO;
use App\Modules\Experiments\Application\DTO\ExperimentFilterDTO;
use App\Modules\Experiments\Application\DTO\UpdateExperimentDTO;
use App\Modules\Experiments\Domain\Contracts\ExperimentRepositoryInterface;
use App\Modules\Experiments\Domain\Enums\ExperimentStatus;
use App\Modules\Experiments\Domain\Enums\ExperimentType;
use App\Modules\Experiments\Domain\Enums\Verdict;
use App\Modules\Experiments\Domain\Exceptions\ExperimentPersistenceFailedException;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

readonly class ExperimentRepository implements ExperimentRepositoryInterface
{
    private const string CODE_PREFIX = 'EXP-';

    private const int CODE_DIGITS = 3;

    public function __construct(private Experiment $model) {}

    public function findAll(ExperimentFilterDTO $filters): Collection|LengthAwarePaginator
    {
        return $filters->perPage > 0
            ? $this->query($filters)->paginate(perPage: $filters->perPage, page: $filters->page)
            : $this->query($filters)->get();
    }

    public function findById(int $id, int $accountId): ?Experiment
    {
        return $this->model->newQuery()
            ->where('account_id', $accountId)
            ->with('warnings')
            ->find($id);
    }

    public function nextCode(int $accountId): string
    {
        $lastCode = $this->model->newQuery()
            ->where('account_id', $accountId)
            ->orderByDesc('id')
            ->value('code');

        return self::CODE_PREFIX.str_pad(
            (string) (((int) substr((string) $lastCode, strlen(self::CODE_PREFIX))) + 1),
            self::CODE_DIGITS,
            '0',
            STR_PAD_LEFT,
        );
    }

    public function existsForStrategy(int $strategyId, int $accountId): bool
    {
        return $this->model->newQuery()
            ->where('account_id', $accountId)
            ->where('strategy_id', $strategyId)
            ->exists();
    }

    public function budgetCommittingForMonth(
        int $strategyId,
        int $accountId,
        CarbonImmutable $month,
        ?int $excludingExperimentId = null,
    ): Collection {
        return $this->model->newQuery()
            ->where('account_id', $accountId)
            ->where('strategy_id', $strategyId)
            ->whereIn('status', $this->budgetCommittingStatuses())
            ->whereBetween('starts_at', [$month->startOfMonth(), $month->endOfMonth()])
            ->when($excludingExperimentId, fn (Builder $query, int $id) => $query->whereKeyNot($id))
            ->get();
    }

    public function create(
        CreateExperimentDTO $dto,
        string $code,
        ?CarbonImmutable $learningPhaseEndsAt,
    ): Experiment {
        try {
            return $this->model->newQuery()->create([
                'account_id' => $dto->accountId,
                'strategy_id' => $dto->strategyId,
                'code' => $code,
                'type' => $dto->type,
                'platform' => $dto->platform,
                'title' => $dto->title,
                'hypothesis' => $dto->hypothesis,
                'expected_result' => $dto->expectedResult,
                'starts_at' => $dto->startsAt,
                'ends_at' => $dto->endsAt,
                'max_budget' => $dto->maxBudget,
                'configuration' => $dto->configuration,
                'status' => $dto->status,
                'learning_phase_ends_at' => $learningPhaseEndsAt,
            ]);
        } catch (Throwable $exception) {
            throw ExperimentPersistenceFailedException::wrap($exception, context: [
                'account_id' => $dto->accountId,
                'strategy_id' => $dto->strategyId,
                'code' => $code,
            ]);
        }
    }

    public function update(
        Experiment $experiment,
        UpdateExperimentDTO $dto,
        ?CarbonImmutable $learningPhaseEndsAt,
    ): Experiment {
        try {
            $experiment->update(array_filter([
                'platform' => $dto->platform,
                'title' => $dto->title,
                'hypothesis' => $dto->hypothesis,
                'expected_result' => $dto->expectedResult,
                'starts_at' => $dto->startsAt,
                'ends_at' => $dto->endsAt,
                'max_budget' => $dto->maxBudget,
                'configuration' => $dto->configuration,
                'status' => $dto->status,
                'production_status' => $dto->productionStatus,
                'learning_phase_ends_at' => $learningPhaseEndsAt,
            ], fn (mixed $value): bool => $value !== null));

            return $experiment->refresh();
        } catch (Throwable $exception) {
            throw ExperimentPersistenceFailedException::wrap($exception, context: [
                'experiment_id' => $experiment->id,
            ]);
        }
    }

    public function setSpendTotal(Experiment $experiment, float $spendTotal): Experiment
    {
        try {
            $experiment->update(['spend_total' => $spendTotal]);

            return $experiment->refresh();
        } catch (Throwable $exception) {
            throw ExperimentPersistenceFailedException::wrap($exception, context: [
                'experiment_id' => $experiment->id,
            ]);
        }
    }

    public function confirmVerdict(
        Experiment $experiment,
        Verdict $verdict,
        string $reason,
        bool $closedEarly,
    ): Experiment {
        try {
            $experiment->update([
                'verdict' => $verdict,
                'verdict_reason' => $reason,
                'verdict_confirmed_at' => CarbonImmutable::now(),
                'status' => ExperimentStatus::Completed,
                'closed_early' => $closedEarly,
            ]);

            return $experiment->refresh();
        } catch (Throwable $exception) {
            throw ExperimentPersistenceFailedException::wrap($exception, context: [
                'experiment_id' => $experiment->id,
                'verdict' => $verdict->value,
            ]);
        }
    }

    public function setStatus(Experiment $experiment, ExperimentStatus $status, bool $closedEarly): Experiment
    {
        try {
            $experiment->update(['status' => $status, 'closed_early' => $closedEarly]);

            return $experiment->refresh();
        } catch (Throwable $exception) {
            throw ExperimentPersistenceFailedException::wrap($exception, context: [
                'experiment_id' => $experiment->id,
                'status' => $status->value,
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function budgetCommittingStatuses(): array
    {
        return collect(ExperimentStatus::cases())
            ->filter(fn (ExperimentStatus $status): bool => $status->commitsBudget())
            ->map(fn (ExperimentStatus $status): string => $status->value)
            ->values()
            ->all();
    }

    private function query(ExperimentFilterDTO $filters): Builder
    {
        return $this->model->newQuery()
            ->where('account_id', $filters->accountId)
            ->when($filters->strategyId, fn (Builder $query, int $strategyId) => $query->where('strategy_id', $strategyId))
            ->when($filters->status, fn (Builder $query, ExperimentStatus $status) => $query->where('status', $status))
            ->when($filters->type, fn (Builder $query, ExperimentType $type) => $query->where('type', $type))
            ->when($filters->verdict, fn (Builder $query, Verdict $verdict) => $query->where('verdict', $verdict))
            ->orderByDesc('id');
    }
}
