<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application\DTO;

readonly class RecordLlmUsageDTO
{
    public function __construct(
        public int $accountId,
        public ?int $userId,
        public string $feature,
        public string $provider,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
        public int $cachedInputTokens = 0,
        public float $estimatedCostUsd = 0.0,
        public int $reasoningTokens = 0,
    ) {}
}
