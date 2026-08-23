<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Infrastructure\Repositories;

use App\Modules\Experiments\Domain\Contracts\ExperimentWarningRepositoryInterface;
use App\Modules\Experiments\Domain\Exceptions\ExperimentWarningPersistenceFailedException;
use App\Modules\Experiments\Infrastructure\Persistence\ExperimentWarning;
use Illuminate\Support\Collection;
use Throwable;

readonly class ExperimentWarningRepository implements ExperimentWarningRepositoryInterface
{
    public function __construct(private ExperimentWarning $model) {}

    public function findForExperiment(int $experimentId, int $accountId): Collection
    {
        return $this->model->newQuery()
            ->where('account_id', $accountId)
            ->where('experiment_id', $experimentId)
            ->orderBy('id')
            ->get();
    }

    public function createMany(int $experimentId, int $accountId, Collection $warnings): Collection
    {
        try {
            return $warnings->map(fn (array $warning): ExperimentWarning => $this->model->newQuery()->create(
                array_merge($warning, ['experiment_id' => $experimentId, 'account_id' => $accountId]),
            ));
        } catch (Throwable $exception) {
            throw ExperimentWarningPersistenceFailedException::wrap($exception, context: [
                'experiment_id' => $experimentId,
            ]);
        }
    }
}
