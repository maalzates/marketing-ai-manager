<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Application\DTO;

use App\Modules\Competitors\Domain\Enums\InsightKind;
use App\Modules\Competitors\Domain\Enums\InsightSource;

readonly class CreateInsightDTO
{
    /** @param array<string, mixed> $evidence */
    public function __construct(
        public int $accountId,
        public InsightKind $kind,
        public InsightSource $source,
        public string $title,
        public string $body,
        public array $evidence = [],
        public float $score = 0.0,
        public ?int $strategyId = null,
        public ?int $competitorId = null,
    ) {}
}
