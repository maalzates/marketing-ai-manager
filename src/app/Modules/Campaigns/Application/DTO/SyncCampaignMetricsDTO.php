<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\DTO;

use Carbon\CarbonImmutable;

readonly class SyncCampaignMetricsDTO
{
    public function __construct(
        public int $accountId,
        public int $experimentId,
        public ?CarbonImmutable $since = null,
        public ?CarbonImmutable $until = null,
    ) {}
}
