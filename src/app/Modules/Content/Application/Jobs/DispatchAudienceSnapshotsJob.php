<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Jobs;

use App\Modules\Content\Application\Services\OwnMetricsImportService;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Fans the daily follower snapshot out per account. Only accounts that have published through the
 * app are swept: nobody else has a series worth building.
 */
class DispatchAudienceSnapshotsJob implements ShouldQueue
{
    use Queueable;

    public function handle(OwnMetricsImportService $service, Dispatcher $bus): void
    {
        $service->accountsWithPublishedContent()
            ->each(fn (int $accountId) => $bus->dispatch(new SnapshotChannelAudienceJob($accountId)));
    }
}
