<?php

declare(strict_types=1);

namespace App\Modules\Strategies\Application\DTO;

/**
 * No status field on purpose: the lifecycle moves only through activate/pause/archive,
 * so no caller can archive or revive a strategy as a side effect of editing it.
 */
readonly class UpdateStrategyDTO
{
    /**
     * @param  list<string>|null  $constraints
     * @param  array<string, mixed>|null  $guardianConfig
     * @param  array<string, mixed>|null  $organicCadence
     */
    public function __construct(
        public int $accountId,
        public int $strategyId,
        public ?string $name = null,
        public ?string $objective = null,
        public ?string $northStarMetric = null,
        public ?float $monthlyBudget = null,
        public ?array $constraints = null,
        public ?array $guardianConfig = null,
        public ?array $organicCadence = null,
    ) {}
}
