<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Infrastructure\Adapters;

use App\Modules\Experiments\Domain\Contracts\ExperimentRepositoryInterface;
use App\Modules\Strategies\Domain\Contracts\StrategyWorkloadProviderInterface;

/**
 * Experiments answering the question Strategies asks before deleting a strategy.
 *
 * It depends on the repository rather than on ExperimentService on purpose: StrategyService
 * constructor-injects this port, and ExperimentService constructor-injects StrategyService,
 * so routing through the Service would rebuild the very container cycle this port exists to
 * remove. The question is a pure existence read with no business rule attached, so the
 * repository is the honest dependency, not a shortcut around one.
 */
readonly class StrategyWorkloadProvider implements StrategyWorkloadProviderInterface
{
    public function __construct(private ExperimentRepositoryInterface $repository) {}

    public function hasRecordedWork(int $strategyId, int $accountId): bool
    {
        return $this->repository->existsForStrategy($strategyId, $accountId);
    }
}
