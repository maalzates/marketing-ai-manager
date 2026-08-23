<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application\DTO;

readonly class RecordApifyUsageDTO
{
    public function __construct(
        public int $accountId,
        public string $actorId,
        public ?string $runId = null,
        public int $resultsCount = 0,
        public float $estimatedCostUsd = 0.0,
    ) {}
}
