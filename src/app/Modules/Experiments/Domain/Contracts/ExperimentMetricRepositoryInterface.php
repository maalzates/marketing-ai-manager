<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Domain\Contracts;

use App\Modules\Experiments\Application\DTO\RecordMetricsDTO;
use App\Modules\Experiments\Infrastructure\Persistence\ExperimentMetric;
use Illuminate\Support\Collection;

interface ExperimentMetricRepositoryInterface
{
    /**
     * @return Collection<int, ExperimentMetric>
     */
    public function findForExperiment(int $experimentId, int $accountId): Collection;

    public function upsertForDate(RecordMetricsDTO $dto): ExperimentMetric;

    public function sumSpend(int $experimentId, int $accountId): float;
}
