<?php

declare(strict_types=1);

namespace App\Modules\Strategies\Infrastructure\Repositories;

use App\Modules\Strategies\Application\DTO\CreateStrategyDTO;
use App\Modules\Strategies\Application\DTO\StrategyFilterDTO;
use App\Modules\Strategies\Application\DTO\UpdateStrategyDTO;
use App\Modules\Strategies\Domain\Contracts\StrategyRepositoryInterface;
use App\Modules\Strategies\Domain\Enums\StrategyStatus;
use App\Modules\Strategies\Domain\Exceptions\StrategyPersistenceFailedException;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

readonly class StrategyRepository implements StrategyRepositoryInterface
{
    public function __construct(private Strategy $model) {}

    public function findAll(StrategyFilterDTO $filters): Collection|LengthAwarePaginator
    {
        return $filters->perPage > 0
            ? $this->query($filters)->paginate(perPage: $filters->perPage, page: $filters->page)
            : $this->query($filters)->get();
    }

    public function findById(int $id, int $accountId): ?Strategy
    {
        return $this->model->newQuery()->where('account_id', $accountId)->find($id);
    }

    public function create(CreateStrategyDTO $dto): Strategy
    {
        try {
            return $this->model->newQuery()->create(array_filter([
                'account_id' => $dto->accountId,
                'brand_profile_id' => $dto->brandProfileId,
                'name' => $dto->name,
                'objective' => $dto->objective,
                'north_star_metric' => $dto->northStarMetric,
                'monthly_budget' => $dto->monthlyBudget,
                'constraints' => $dto->constraints,
                'guardian_config' => $dto->guardianConfig,
                'organic_cadence' => $dto->organicCadence,
            ], fn (mixed $value): bool => $value !== null));
        } catch (Throwable $exception) {
            throw StrategyPersistenceFailedException::wrap($exception, context: [
                'account_id' => $dto->accountId,
                'name' => $dto->name,
            ]);
        }
    }

    public function update(Strategy $strategy, UpdateStrategyDTO $dto): Strategy
    {
        try {
            $strategy->update(array_filter([
                'name' => $dto->name,
                'objective' => $dto->objective,
                'north_star_metric' => $dto->northStarMetric,
                'monthly_budget' => $dto->monthlyBudget,
                'constraints' => $dto->constraints,
                'guardian_config' => $dto->guardianConfig,
                'organic_cadence' => $dto->organicCadence,
            ], fn (mixed $value): bool => $value !== null));

            return $strategy->refresh();
        } catch (Throwable $exception) {
            throw StrategyPersistenceFailedException::wrap($exception, context: [
                'strategy_id' => $strategy->id,
            ]);
        }
    }

    public function changeStatus(Strategy $strategy, StrategyStatus $status): Strategy
    {
        try {
            $strategy->update(['status' => $status]);

            return $strategy->refresh();
        } catch (Throwable $exception) {
            throw StrategyPersistenceFailedException::wrap($exception, context: [
                'strategy_id' => $strategy->id,
                'status' => $status->value,
            ]);
        }
    }

    public function delete(Strategy $strategy): bool
    {
        try {
            return (bool) $strategy->delete();
        } catch (Throwable $exception) {
            throw StrategyPersistenceFailedException::wrap($exception, context: [
                'strategy_id' => $strategy->id,
            ]);
        }
    }

    public function hasStrategiesForBrandProfile(int $brandProfileId, int $accountId): bool
    {
        return $this->model->newQuery()
            ->where('account_id', $accountId)
            ->where('brand_profile_id', $brandProfileId)
            ->exists();
    }

    private function query(StrategyFilterDTO $filters): Builder
    {
        return $this->model->newQuery()
            ->where('account_id', $filters->accountId)
            ->when(
                $filters->status,
                fn (Builder $query, StrategyStatus $status): Builder => $query->where('status', $status->value),
            )
            ->orderByDesc('id');
    }
}
