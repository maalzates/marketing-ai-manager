<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Infrastructure\Repositories;

use App\Modules\Reporting\Application\DTO\CreateReportDTO;
use App\Modules\Reporting\Application\DTO\ReportFilterDTO;
use App\Modules\Reporting\Domain\Contracts\ReportRepositoryInterface;
use App\Modules\Reporting\Domain\Enums\ReportType;
use App\Modules\Reporting\Domain\Exceptions\ReportPersistenceFailedException;
use App\Modules\Reporting\Infrastructure\Persistence\Report;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

readonly class ReportRepository implements ReportRepositoryInterface
{
    public function __construct(private Report $model) {}

    public function findAll(ReportFilterDTO $filters): Collection|LengthAwarePaginator
    {
        return $filters->perPage > 0
            ? $this->query($filters)->paginate(perPage: $filters->perPage, page: $filters->page)
            : $this->query($filters)->get();
    }

    public function findById(int $id, int $accountId): ?Report
    {
        return $this->model->newQuery()->where('account_id', $accountId)->find($id);
    }

    public function findVerdictReport(int $experimentId, int $accountId): ?Report
    {
        return $this->model->newQuery()
            ->where('account_id', $accountId)
            ->where('experiment_id', $experimentId)
            ->where('type', ReportType::ExperimentVerdict)
            ->first();
    }

    public function findPeriodicReport(
        int $strategyId,
        int $accountId,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
    ): ?Report {
        return $this->model->newQuery()
            ->where('account_id', $accountId)
            ->where('strategy_id', $strategyId)
            ->where('type', ReportType::Periodic)
            ->whereDate('period_start', $periodStart)
            ->whereDate('period_end', $periodEnd)
            ->first();
    }

    public function create(CreateReportDTO $dto): Report
    {
        try {
            return $this->model->newQuery()->create([
                'account_id' => $dto->accountId,
                'strategy_id' => $dto->strategyId,
                'experiment_id' => $dto->experimentId,
                'type' => $dto->type,
                'period_start' => $dto->periodStart,
                'period_end' => $dto->periodEnd,
                'body' => $dto->body,
                'data' => $dto->data,
                'generated_at' => $dto->generatedAt,
            ]);
        } catch (Throwable $exception) {
            throw ReportPersistenceFailedException::wrap($exception, context: [
                'account_id' => $dto->accountId,
                'type' => $dto->type->value,
                'experiment_id' => $dto->experimentId,
                'strategy_id' => $dto->strategyId,
            ]);
        }
    }

    private function query(ReportFilterDTO $filters): Builder
    {
        return $this->model->newQuery()
            ->where('account_id', $filters->accountId)
            ->when($filters->strategyId, fn (Builder $query, int $strategyId) => $query->where('strategy_id', $strategyId))
            ->when($filters->experimentId, fn (Builder $query, int $experimentId) => $query->where('experiment_id', $experimentId))
            ->when($filters->type, fn (Builder $query, ReportType $type) => $query->where('type', $type))
            ->orderByDesc('generated_at');
    }
}
