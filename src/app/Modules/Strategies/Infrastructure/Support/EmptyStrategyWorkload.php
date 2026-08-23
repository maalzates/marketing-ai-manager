<?php

declare(strict_types=1);

namespace App\Modules\Strategies\Infrastructure\Support;

use App\Modules\Strategies\Domain\Contracts\StrategyWorkloadProviderInterface;

/**
 * The default when no module has claimed the work under a strategy yet, so Strategies boots
 * and reads on its own. A deployment that owns experiments overrides this binding.
 */
readonly class EmptyStrategyWorkload implements StrategyWorkloadProviderInterface
{
    public function hasRecordedWork(int $strategyId, int $accountId): bool
    {
        return false;
    }
}
