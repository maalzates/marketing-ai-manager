<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Jobs;

use App\Modules\Campaigns\Application\Services\CampaignService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * The scheduler's only entry point into campaign metrics. Parameterless on purpose: it fans
 * out to one SyncCampaignMetricsJob per campaign rather than meaning something different
 * depending on an argument.
 *
 * Safe to run as often as the schedule likes — the daily rows are upserted by
 * (experiment_id, date), so a second run in the same hour corrects a day instead of
 * duplicating it.
 */
class DispatchCampaignMetricsSyncJob implements ShouldQueue
{
    use Queueable;

    public function handle(CampaignService $service): void
    {
        $service->dispatchMetricsSync();
    }
}
