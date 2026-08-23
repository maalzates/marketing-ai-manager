<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Infrastructure\Repositories;

use App\Modules\Competitors\Application\DTO\CompetitorFilterDTO;
use App\Modules\Competitors\Application\DTO\CreateCompetitorDTO;
use App\Modules\Competitors\Domain\Contracts\CompetitorRepositoryInterface;
use App\Modules\Competitors\Domain\Enums\CompetitorPlatform;
use App\Modules\Competitors\Domain\Exceptions\CompetitorPersistenceFailedException;
use App\Modules\Competitors\Infrastructure\Persistence\Competitor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

readonly class CompetitorRepository implements CompetitorRepositoryInterface
{
    public function __construct(private Competitor $model) {}

    public function findAll(CompetitorFilterDTO $filters): Collection|LengthAwarePaginator
    {
        return $filters->perPage > 0
            ? $this->query($filters)->paginate(perPage: $filters->perPage, page: $filters->page)
            : $this->query($filters)->get();
    }

    public function findById(int $id, int $accountId): ?Competitor
    {
        return $this->model->newQuery()->where('account_id', $accountId)->find($id);
    }

    public function findByHandle(int $accountId, CompetitorPlatform $platform, string $handle): ?Competitor
    {
        return $this->model->newQuery()
            ->where('account_id', $accountId)
            ->where('platform', $platform->value)
            ->where('handle', $handle)
            ->first();
    }

    public function create(CreateCompetitorDTO $dto): Competitor
    {
        try {
            return $this->model->newQuery()->create([
                'account_id' => $dto->accountId,
                'strategy_id' => $dto->strategyId,
                'platform' => $dto->platform,
                'handle' => $dto->handle,
                'display_name' => $dto->displayName,
                'external_id' => $dto->externalId,
            ]);
        } catch (Throwable $exception) {
            throw CompetitorPersistenceFailedException::wrap($exception, context: [
                'account_id' => $dto->accountId,
                'platform' => $dto->platform->value,
                'handle' => $dto->handle,
            ]);
        }
    }

    public function delete(Competitor $competitor): bool
    {
        return (bool) $competitor->delete();
    }

    public function markSynced(Competitor $competitor): Competitor
    {
        try {
            $competitor->update(['last_synced_at' => now()]);

            return $competitor->refresh();
        } catch (Throwable $exception) {
            throw CompetitorPersistenceFailedException::wrap($exception, context: [
                'competitor_id' => $competitor->id,
            ]);
        }
    }

    public function activeForAccount(int $accountId): Collection
    {
        return $this->model->newQuery()
            ->where('account_id', $accountId)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
    }

    public function accountIdsWithActiveCompetitors(): Collection
    {
        return $this->model->newQuery()
            ->where('is_active', true)
            ->distinct()
            ->pluck('account_id')
            ->map(static fn (mixed $accountId): int => (int) $accountId);
    }

    private function query(CompetitorFilterDTO $filters): Builder
    {
        return $this->model->newQuery()
            ->where('account_id', $filters->accountId)
            ->when(
                $filters->platform,
                fn (Builder $query, CompetitorPlatform $platform): Builder => $query->where('platform', $platform->value),
            )
            ->when($filters->strategyId, fn (Builder $query, int $strategyId): Builder => $query->where('strategy_id', $strategyId))
            ->when($filters->isActive !== null, fn (Builder $query): Builder => $query->where('is_active', $filters->isActive))
            ->orderByDesc('id');
    }
}
