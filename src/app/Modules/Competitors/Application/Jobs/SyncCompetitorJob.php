<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Application\Jobs;

use App\Modules\Competitors\Application\Services\CompetitorSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncCompetitorJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $accountId, private readonly int $competitorId) {}

    public function handle(CompetitorSyncService $service): void
    {
        $service->syncPosts($this->accountId, $this->competitorId);
    }
}
