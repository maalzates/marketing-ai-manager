<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Application\DTO;

use Carbon\CarbonImmutable;

readonly class RecordMetricsDTO
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public int $accountId,
        public int $experimentId,
        public CarbonImmutable $date,
        public float $spend,
        public int $impressions,
        public int $reach,
        public int $clicks,
        public float $ctr,
        public float $cpm,
        public float $cpc,
        public int $conversions,
        public ?float $cpa,
        public ?float $frequency,
        public int $videoViews,
        public int $engagement,
        public array $raw = [],
    ) {}
}
