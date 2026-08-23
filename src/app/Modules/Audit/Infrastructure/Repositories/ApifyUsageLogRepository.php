<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Repositories;

use App\Modules\Audit\Application\DTO\RecordApifyUsageDTO;
use App\Modules\Audit\Application\DTO\UsageFilterDTO;
use App\Modules\Audit\Domain\Contracts\ApifyUsageLogRepositoryInterface;
use App\Modules\Audit\Domain\Enums\UsageGrouping;
use App\Modules\Audit\Domain\Exceptions\UsageLogWriteFailedException;
use App\Modules\Audit\Infrastructure\Persistence\ApifyUsageLog;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Throwable;

readonly class ApifyUsageLogRepository implements ApifyUsageLogRepositoryInterface
{
    public function __construct(private ApifyUsageLog $model) {}

    public function create(RecordApifyUsageDTO $dto): ApifyUsageLog
    {
        try {
            return $this->model->newQuery()->create([
                'account_id' => $dto->accountId,
                'actor_id' => $dto->actorId,
                'run_id' => $dto->runId,
                'results_count' => $dto->resultsCount,
                'estimated_cost_usd' => $dto->estimatedCostUsd,
            ]);
        } catch (Throwable $exception) {
            throw UsageLogWriteFailedException::wrap($exception, context: [
                'account_id' => $dto->accountId,
                'actor_id' => $dto->actorId,
            ]);
        }
    }

    public function summary(UsageFilterDTO $filters): Collection
    {
        return $this->query($filters)
            ->selectRaw($this->label($filters).' as label')
            ->selectRaw('COUNT(*) as calls')
            ->selectRaw('SUM(results_count) as results')
            ->selectRaw('SUM(estimated_cost_usd) as cost_usd')
            ->groupBy('label')
            ->orderBy('label')
            ->get()
            ->map(fn (object $row): array => [
                'label' => (string) $row->label,
                'calls' => (int) $row->calls,
                'results' => (int) $row->results,
                'cost_usd' => (float) $row->cost_usd,
            ]);
    }

    /**
     * Apify runs carry no feature: the actor is the closest analogue when the caller
     * asks for a per-feature breakdown.
     */
    private function label(UsageFilterDTO $filters): string
    {
        return match ($filters->groupBy) {
            UsageGrouping::FEATURE => 'actor_id',
            UsageGrouping::ACCOUNT => 'account_id',
            UsageGrouping::DAY => 'DATE(created_at)',
        };
    }

    private function query(UsageFilterDTO $filters): Builder
    {
        return $this->model->newQuery()
            ->when(
                $filters->accountId !== null,
                fn (EloquentBuilder $query) => $query->where('account_id', $filters->accountId),
            )
            ->whereBetween('created_at', [$filters->from, $filters->to])
            ->toBase();
    }
}
