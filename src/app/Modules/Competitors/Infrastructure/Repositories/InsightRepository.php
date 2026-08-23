<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Infrastructure\Repositories;

use App\Modules\Competitors\Application\DTO\CreateInsightDTO;
use App\Modules\Competitors\Application\DTO\InsightFilterDTO;
use App\Modules\Competitors\Domain\Contracts\InsightRepositoryInterface;
use App\Modules\Competitors\Domain\Enums\InsightKind;
use App\Modules\Competitors\Domain\Enums\InsightStatus;
use App\Modules\Competitors\Domain\Exceptions\InsightPersistenceFailedException;
use App\Modules\Competitors\Infrastructure\Persistence\Insight;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

readonly class InsightRepository implements InsightRepositoryInterface
{
    public function __construct(private Insight $model) {}

    public function findAll(InsightFilterDTO $filters): Collection|LengthAwarePaginator
    {
        return $filters->perPage > 0
            ? $this->query($filters)->paginate(perPage: $filters->perPage, page: $filters->page)
            : $this->query($filters)->get();
    }

    public function findById(int $id, int $accountId): ?Insight
    {
        return $this->model->newQuery()->where('account_id', $accountId)->find($id);
    }

    public function create(CreateInsightDTO $dto): Insight
    {
        try {
            return $this->model->newQuery()->create([
                'account_id' => $dto->accountId,
                'strategy_id' => $dto->strategyId,
                'competitor_id' => $dto->competitorId,
                'kind' => $dto->kind,
                'source' => $dto->source,
                'title' => $dto->title,
                'body' => $dto->body,
                'evidence' => $dto->evidence,
                'score' => $dto->score,
            ]);
        } catch (Throwable $exception) {
            throw InsightPersistenceFailedException::wrap($exception, context: [
                'account_id' => $dto->accountId,
                'kind' => $dto->kind->value,
                'title' => $dto->title,
            ]);
        }
    }

    public function changeStatus(Insight $insight, InsightStatus $status): Insight
    {
        try {
            $insight->update(['status' => $status]);

            return $insight->refresh();
        } catch (Throwable $exception) {
            throw InsightPersistenceFailedException::wrap($exception, context: [
                'insight_id' => $insight->id,
                'status' => $status->value,
            ]);
        }
    }

    public function titlesOfKind(int $accountId, InsightKind $kind): Collection
    {
        return $this->model->newQuery()
            ->where('account_id', $accountId)
            ->where('kind', $kind->value)
            ->pluck('title')
            ->map(static fn (mixed $title): string => (string) $title);
    }

    private function query(InsightFilterDTO $filters): Builder
    {
        return $this->model->newQuery()
            ->where('account_id', $filters->accountId)
            ->when($filters->kind, fn (Builder $query, InsightKind $kind): Builder => $query->where('kind', $kind->value))
            ->when($filters->status, fn (Builder $query, InsightStatus $status): Builder => $query->where('status', $status->value))
            ->when($filters->strategyId, fn (Builder $query, int $strategyId): Builder => $query->where('strategy_id', $strategyId))
            ->when($filters->competitorId, fn (Builder $query, int $competitorId): Builder => $query->where('competitor_id', $competitorId))
            ->orderByDesc('score')
            ->orderByDesc('id');
    }
}
