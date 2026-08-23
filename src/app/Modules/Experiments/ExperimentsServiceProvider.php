<?php

declare(strict_types=1);

namespace App\Modules\Experiments;

use App\Modules\Experiments\Domain\Contracts\ExperimentMetricRepositoryInterface;
use App\Modules\Experiments\Domain\Contracts\ExperimentRepositoryInterface;
use App\Modules\Experiments\Domain\Contracts\ExperimentWarningRepositoryInterface;
use App\Modules\Experiments\Infrastructure\Adapters\StrategyWorkloadProvider;
use App\Modules\Experiments\Infrastructure\Repositories\ExperimentMetricRepository;
use App\Modules\Experiments\Infrastructure\Repositories\ExperimentRepository;
use App\Modules\Experiments\Infrastructure\Repositories\ExperimentWarningRepository;
use App\Modules\Strategies\Domain\Contracts\StrategyWorkloadProviderInterface;
use Illuminate\Support\ServiceProvider;

class ExperimentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ExperimentRepositoryInterface::class, ExperimentRepository::class);
        $this->app->bind(ExperimentWarningRepositoryInterface::class, ExperimentWarningRepository::class);
        $this->app->bind(ExperimentMetricRepositoryInterface::class, ExperimentMetricRepository::class);

        // Plain bind, not bindIf: Strategies registers a null object with bindIf and this must
        // beat it. A silent "no work recorded" would let every strategy delete its own history.
        $this->app->bind(StrategyWorkloadProviderInterface::class, StrategyWorkloadProvider::class);
    }
}
