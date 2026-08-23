<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Domain\ValueObjects;

use App\Modules\Experiments\Infrastructure\Persistence\ExperimentMetric;
use Illuminate\Support\Collection;

/**
 * The daily series collapsed into the numbers a verdict is argued from. Ratios are
 * recomputed from the totals instead of averaged: the mean of daily CTRs is not the CTR
 * of the period, and a verdict built on it would be wrong on uneven delivery.
 */
readonly class MetricTotals
{
    public function __construct(
        public int $days,
        public float $spend,
        public int $impressions,
        public int $reach,
        public int $clicks,
        public int $conversions,
        public int $videoViews,
        public int $engagement,
    ) {}

    /**
     * @param  Collection<int, ExperimentMetric>  $daily
     */
    public static function fromDaily(Collection $daily): self
    {
        return new self(
            $daily->count(),
            (float) $daily->sum('spend'),
            (int) $daily->sum('impressions'),
            (int) $daily->max('reach'),
            (int) $daily->sum('clicks'),
            (int) $daily->sum('conversions'),
            (int) $daily->sum('video_views'),
            (int) $daily->sum('engagement'),
        );
    }

    public function valueOf(string $metric): ?float
    {
        return match ($metric) {
            'spend' => $this->spend,
            'impressions' => (float) $this->impressions,
            'reach' => (float) $this->reach,
            'clicks' => (float) $this->clicks,
            'conversions' => (float) $this->conversions,
            'video_views' => (float) $this->videoViews,
            'engagement' => (float) $this->engagement,
            'ctr' => $this->ratio($this->clicks * 100, $this->impressions),
            'engagement_rate' => $this->ratio($this->engagement * 100, $this->impressions),
            'cpm' => $this->ratio($this->spend * 1000, $this->impressions),
            'cpc' => $this->ratio($this->spend, $this->clicks),
            'cpa', 'cpl', 'cost_per_lead', 'cost_per_follower' => $this->ratio($this->spend, $this->conversions),
            'frequency' => $this->ratio((float) $this->impressions, $this->reach),
            default => null,
        };
    }

    private function ratio(float $numerator, int $denominator): ?float
    {
        return $denominator > 0 ? $numerator / $denominator : null;
    }
}
