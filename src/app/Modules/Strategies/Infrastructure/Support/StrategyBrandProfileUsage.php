<?php

declare(strict_types=1);

namespace App\Modules\Strategies\Infrastructure\Support;

use App\Modules\Brands\Domain\Contracts\BrandProfileUsageProviderInterface;
use App\Modules\Strategies\Domain\Contracts\StrategyRepositoryInterface;

/**
 * Strategies answering Brands' question. It reads the repository rather than StrategyService
 * on purpose: StrategyService depends on BrandProfileService, and BrandProfileService is what
 * resolves this adapter — going through the Service would close that loop into a cycle.
 */
readonly class StrategyBrandProfileUsage implements BrandProfileUsageProviderInterface
{
    public function __construct(private StrategyRepositoryInterface $repository) {}

    public function isInUse(int $brandProfileId, int $accountId): bool
    {
        return $this->repository->hasStrategiesForBrandProfile($brandProfileId, $accountId);
    }
}
