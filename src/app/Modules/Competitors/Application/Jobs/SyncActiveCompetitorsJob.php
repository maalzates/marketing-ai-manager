<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Application\Jobs;

use App\Modules\Competitors\Application\Services\CompetitorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncActiveCompetitorsJob implements ShouldQueue
{
    use Queueable;

    public function handle(CompetitorService $service): void
    {
        $service->dispatchDailySync();
    }
}
