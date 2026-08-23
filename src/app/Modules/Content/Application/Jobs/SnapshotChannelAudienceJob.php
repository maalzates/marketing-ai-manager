<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Jobs;

use App\Modules\Content\Application\Services\OwnMetricsImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * `follower_count` no longer exists as an insight metric, so the only follower time series that
 * will ever exist is the one this job writes, one row per account per day.
 */
class SnapshotChannelAudienceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $accountId) {}

    public function handle(OwnMetricsImportService $service): void
    {
        $service->snapshotAudience($this->accountId);
    }
}
