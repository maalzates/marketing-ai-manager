<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Jobs;

use App\Modules\Content\Application\Services\PublishingService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * One attempt per dispatch: whether to try again is a decision about the calendar, not about the
 * queue, so a transient failure puts the piece back on the calendar instead of silently
 * re-running here.
 */
class PublishScheduledContentJob implements ShouldQueue
{
    use Queueable;

    /** Instagram's numbers are still settling right after a post; the first read waits a day. */
    private const int METRICS_DELAY_HOURS = 24;

    public int $tries = 1;

    /** Long enough for the documented five minutes of container polling, plus the ingestion wait. */
    public int $timeout = 600;

    public function __construct(private readonly int $accountId, private readonly int $scheduleId) {}

    public function handle(PublishingService $service, Dispatcher $bus): void
    {
        $schedule = $service->publish($this->accountId, $this->scheduleId);

        if ($schedule->external_post_id !== null) {
            $bus->dispatch(
                (new ImportOwnMetricsJob($this->accountId, (int) $schedule->id))
                    ->delay(CarbonImmutable::now()->addHours(self::METRICS_DELAY_HOURS)),
            );
        }
    }

    public function failed(Throwable $exception): void
    {
        app(PublishingService::class)->handleFailure($this->accountId, $this->scheduleId, $exception);
    }
}
