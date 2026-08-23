<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Infrastructure\Repositories;

use App\Modules\Experiments\Application\DTO\RecordMetricsDTO;
use App\Modules\Experiments\Domain\Contracts\ExperimentMetricRepositoryInterface;
use App\Modules\Experiments\Domain\Exceptions\ExperimentMetricPersistenceFailedException;
use App\Modules\Experiments\Infrastructure\Persistence\ExperimentMetric;
use Illuminate\Support\Collection;
use Throwable;

readonly class ExperimentMetricRepository implements ExperimentMetricRepositoryInterface
{
    public function __construct(private ExperimentMetric $model) {}

    public function findForExperiment(int $experimentId, int $accountId): Collection
    {
        return $this->model->newQuery()
            ->where('account_id', $accountId)
            ->where('experiment_id', $experimentId)
            ->orderBy('date')
            ->get();
    }

    /** A provider re-reports a day as often as it likes; the day is the identity, not the row. */
    public function upsertForDate(RecordMetricsDTO $dto): ExperimentMetric
    {
        try {
            return $this->model->newQuery()->updateOrCreate(
                ['experiment_id' => $dto->experimentId, 'date' => $dto->date->toDateString()],
                [
                    'account_id' => $dto->accountId,
                    'spend' => $dto->spend,
                    'impressions' => $dto->impressions,
                    'reach' => $dto->reach,
                    'clicks' => $dto->clicks,
                    'ctr' => $dto->ctr,
                    'cpm' => $dto->cpm,
                    'cpc' => $dto->cpc,
                    'conversions' => $dto->conversions,
                    'cpa' => $dto->cpa,
                    'frequency' => $dto->frequency,
                    'video_views' => $dto->videoViews,
                    'engagement' => $dto->engagement,
                    'raw' => $dto->raw,
                ],
            );
        } catch (Throwable $exception) {
            throw ExperimentMetricPersistenceFailedException::wrap($exception, context: [
                'experiment_id' => $dto->experimentId,
                'date' => $dto->date->toDateString(),
            ]);
        }
    }

    public function sumSpend(int $experimentId, int $accountId): float
    {
        return (float) $this->model->newQuery()
            ->where('account_id', $accountId)
            ->where('experiment_id', $experimentId)
            ->sum('spend');
    }
}
