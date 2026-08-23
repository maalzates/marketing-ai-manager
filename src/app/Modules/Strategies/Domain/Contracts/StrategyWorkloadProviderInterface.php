<?php

declare(strict_types=1);

namespace App\Modules\Strategies\Domain\Contracts;

/**
 * What Strategies needs to know about the work carried out under a strategy, phrased as
 * the question this module actually asks. The module that owns that work implements it and
 * binds itself; Strategies never learns who that is.
 */
interface StrategyWorkloadProviderInterface
{
    /**
     * Whether the strategy already carries work — anything planned, running or finished —
     * that deleting it would erase along with it.
     */
    public function hasRecordedWork(int $strategyId, int $accountId): bool;
}
