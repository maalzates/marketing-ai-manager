<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Jobs;

use App\Modules\Content\Application\Services\OwnMetricsImportService;
use App\Modules\Content\Infrastructure\Persistence\ContentSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Re-reads what recent pieces did. One job per piece, so an account whose token expired does not
 * stop every other account's import.
 */
class ImportRecentOwnMetricsJob implements ShouldQueue
{
    use Queueable;

    private const int WINDOW_DAYS = 7;

    public function handle(OwnMetricsImportService $service, Dispatcher $bus): void
    {
        $service->recentlyPublished(CarbonImmutable::now()->subDays(self::WINDOW_DAYS))
            ->each(fn (ContentSchedule $schedule) => $bus->dispatch(
                new ImportOwnMetricsJob((int) $schedule->account_id, (int) $schedule->id),
            ));
    }
}
