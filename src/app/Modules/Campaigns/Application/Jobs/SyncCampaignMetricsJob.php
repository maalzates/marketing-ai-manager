<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Jobs;

use App\Modules\Campaigns\Application\DTO\SyncCampaignMetricsDTO;
use App\Modules\Campaigns\Application\Services\CampaignMetricsSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncCampaignMetricsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly SyncCampaignMetricsDTO $dto) {}

    public function handle(CampaignMetricsSyncService $service): void
    {
        $service->sync($this->dto);
    }
}
