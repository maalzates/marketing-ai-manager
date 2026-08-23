<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Domain\ValueObjects;

use Carbon\CarbonImmutable;

/**
 * One day of delivery, already normalised: the platform returns every metric as a string
 * and the provider is the last layer allowed to know that.
 */
readonly class DailyMetrics
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
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
