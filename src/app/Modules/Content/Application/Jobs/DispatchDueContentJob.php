<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Jobs;

use App\Modules\Content\Application\Services\PublishingService;
use App\Modules\Content\Infrastructure\Persistence\ContentSchedule;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * The scheduler's one-minute sweep. It claims each due slot before fanning out, so a sweep that
 * overlaps the previous one cannot publish the same piece twice.
 */
class DispatchDueContentJob implements ShouldQueue
{
    use Queueable;

    public function handle(PublishingService $service, Dispatcher $bus): void
    {
        $service->claimDue()->each(fn (ContentSchedule $schedule) => $bus->dispatch(
            new PublishScheduledContentJob((int) $schedule->account_id, (int) $schedule->id),
        ));
    }
}
