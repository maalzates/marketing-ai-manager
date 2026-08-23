<?php

declare(strict_types=1);

namespace App\Modules\Strategies\Application\DTO;

readonly class CreateStrategyDTO
{
    /**
     * @param  list<string>|null  $constraints
     * @param  array<string, mixed>|null  $guardianConfig
     * @param  array<string, mixed>|null  $organicCadence
     */
    public function __construct(
        public int $accountId,
        public int $brandProfileId,
        public string $name,
        public string $objective,
        public string $northStarMetric,
        public ?float $monthlyBudget = null,
        public ?array $constraints = null,
        public ?array $guardianConfig = null,
        public ?array $organicCadence = null,
    ) {}
}
