<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\DTO;

use Carbon\CarbonImmutable;

/**
 * The channel-agnostic shape of one published piece's performance. `views` is the metric
 * that replaced Instagram's `impressions`, `plays` and `video_views`, all three of which
 * are dead; nothing here carries a provider-specific name.
 */
readonly class ChannelMetricsDTO
{
    public function __construct(
        public CarbonImmutable $date,
        public int $views,
        public int $reach,
        public int $likes,
        public int $comments,
        public int $shares,
        public int $saved,
        public int $totalInteractions,
        public int $follows,
        public int $profileVisits,
        public array $raw = [],
    ) {}
}
