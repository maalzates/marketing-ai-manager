<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Domain\Contracts;

use App\Modules\Experiments\Infrastructure\Persistence\ExperimentWarning;
use Illuminate\Support\Collection;

interface ExperimentWarningRepositoryInterface
{
    /**
     * @return Collection<int, ExperimentWarning>
     */
    public function findForExperiment(int $experimentId, int $accountId): Collection;

    /**
     * @param  Collection<int, array<string, mixed>>  $warnings
     * @return Collection<int, ExperimentWarning>
     */
    public function createMany(int $experimentId, int $accountId, Collection $warnings): Collection;
}
