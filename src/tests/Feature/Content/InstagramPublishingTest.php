<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Assets\Domain\Enums\AssetType;
use App\Modules\Assets\Infrastructure\Persistence\Asset;
use App\Modules\Content\Application\Jobs\PublishScheduledContentJob;
use App\Modules\Content\Domain\Enums\ScheduleStatus;
use App\Modules\Content\Infrastructure\Persistence\ContentSchedule;
use App\Modules\Experiments\Domain\Enums\ExperimentPlatform;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Sleep;
use Tests\Support\FakeTransport;
use Tests\TestCase;
use Throwable;

/**
 * Instagram publishing is pull-based and asynchronous: we hand Meta a URL, Meta ingests it on
 * its own clock, and only then is there a post. Everything asserted here is about the parts of
 * that dance that cannot be seen from inside the process — what the quota really was, what URL
 * the container was given, and what happens on each of the terminal container states.
 */
class InstagramPublishingTest extends TestCase
{
    use RefreshDatabase;

    private const string IG_USER_ID = '17841400000000001';

    private const string CONTAINER_ID = '17999000000000001';

    private const string MEDIA_ID = '17888000000000001';

    private Account $account;

    private ContentSchedule $schedule;

    private FakeTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-08 12:00:00'));
        Sleep::fake();

        $this->transport = FakeTransport::silent()->install($this->app);
        $this->account = Account::factory()->create();
        Integration::factory()->meta()->create(['account_id' => $this->account->id]);

        $experiment = Experiment::factory()->organic()->running()->create([
            'account_id' => $this->account->id,
            'strategy_id' => Strategy::factory()->create(['account_id' => $this->account->id])->id,
            'platform' => ExperimentPlatform::Instagram,
        ]);

        $this->schedule = ContentSchedule::factory()->due()->create([
            'account_id' => $this->account->id,
            'experiment_id' => $experiment->id,
            'platform' => ExperimentPlatform::Instagram,
            'asset_id' => $this->reel()->id,
        ]);
    }

    /**
     * Meta's own documentation gives two different numbers for this quota, so the only
     * trustworthy source is the account's own answer — never a constant in our code.
     */
    public function test_reads_the_publishing_quota_from_the_accounts_own_limit_endpoint(): void
    {
        $this->queueSuccessfulPublish(quotaUsage: 6, quotaTotal: 7);

        $this->publish();

        $this->assertStringContainsString('content_publishing_limit', $this->transport->path(1));
        $this->assertSame(ScheduleStatus::Published, $this->schedule->fresh()->status);
    }

    /** Seven is not 25, 50 or 100: the ceiling that stops a publish is the one the API reported. */
    public function test_postpones_the_piece_when_the_reported_quota_is_exhausted(): void
    {
        $this->queuePages();
        $this->queueLimit(quotaUsage: 7, quotaTotal: 7);

        $this->publish();

        $schedule = $this->schedule->fresh();

        $this->assertSame(ScheduleStatus::Pending, $schedule->status);
        $this->assertSame('publishing_quota_exhausted', $schedule->last_error);
        $this->assertSame('2026-09-08 13:00:00', $schedule->scheduled_at->format('Y-m-d H:i:s'));
        $this->assertSame(2, $this->transport->requestCount());
    }

    /** A usage of 24 would publish under a hardcoded 25 and stall under a hardcoded 20. */
    public function test_publishes_while_the_reported_quota_still_has_headroom(): void
    {
        $this->queueSuccessfulPublish(quotaUsage: 24, quotaTotal: 100);

        $this->publish();

        $this->assertSame(ScheduleStatus::Published, $this->schedule->fresh()->status);
    }

    public function test_gives_meta_the_signed_media_url_rather_than_the_bytes(): void
    {
        $this->queueSuccessfulPublish();

        $this->publish();

        parse_str($this->transport->query(3), $parameters);

        $this->assertSame('REELS', $parameters['media_type']);
        $this->assertStringContainsString('/media/', $parameters['video_url']);
        $this->assertSame('', $this->transport->body(3));
    }

    public function test_polls_the_container_until_it_reports_finished(): void
    {
        $this->queuePages();
        $this->queueLimit();
        $this->queuePages();
        $this->transport->queue(
            FakeTransport::json(['id' => self::CONTAINER_ID]),
            FakeTransport::json(['id' => self::CONTAINER_ID, 'status_code' => 'IN_PROGRESS']),
            FakeTransport::json(['id' => self::CONTAINER_ID, 'status_code' => 'IN_PROGRESS']),
            FakeTransport::json(['id' => self::CONTAINER_ID, 'status_code' => 'FINISHED']),
            FakeTransport::json(['id' => self::MEDIA_ID]),
            FakeTransport::json(['id' => self::MEDIA_ID, 'permalink' => 'https://www.instagram.com/reel/XYZ/']),
        );

        $this->publish();

        $this->assertSame(self::MEDIA_ID, $this->schedule->fresh()->external_post_id);
        Sleep::assertSleptTimes(2);
    }

    public function test_marks_the_piece_failed_when_the_container_reports_error(): void
    {
        $this->queueTerminalContainer('ERROR');

        $this->publish();

        $schedule = $this->schedule->fresh();

        $this->assertSame(ScheduleStatus::Failed, $schedule->status);
        $this->assertStringContainsString('manual_publish_required', (string) $schedule->last_error);
        $this->assertNull($schedule->external_post_id);
    }

    public function test_marks_the_piece_failed_when_the_container_expires(): void
    {
        $this->queueTerminalContainer('EXPIRED');

        $this->publish();

        $this->assertSame(ScheduleStatus::Failed, $this->schedule->fresh()->status);
        $this->assertStringContainsString('manual_publish_required', (string) $this->schedule->fresh()->last_error);
    }

    /** Five minutes of IN_PROGRESS is slow, not broken, so the slot goes back on the calendar. */
    public function test_puts_the_piece_back_on_the_calendar_when_the_container_never_finishes(): void
    {
        $this->queuePages();
        $this->queueLimit();
        $this->queuePages();
        $this->transport->queue(FakeTransport::json(['id' => self::CONTAINER_ID]));

        foreach (range(1, 5) as $ignored) {
            $this->transport->queue(FakeTransport::json(['id' => self::CONTAINER_ID, 'status_code' => 'IN_PROGRESS']));
        }

        $this->publish();

        $schedule = $this->schedule->fresh();

        $this->assertSame(ScheduleStatus::Pending, $schedule->status);
        $this->assertSame('2026-09-08 12:15:00', $schedule->scheduled_at->format('Y-m-d H:i:s'));
    }

    public function test_never_publishes_the_same_piece_twice(): void
    {
        $this->schedule->update(['status' => ScheduleStatus::Published, 'external_post_id' => self::MEDIA_ID]);
        $this->queueMetricsImport();

        $this->publish();

        $this->assertSame([], array_filter(
            array_map(fn (int $i): string => $this->transport->path($i), range(0, $this->transport->requestCount() - 1)),
            fn (string $path): bool => str_contains($path, 'media_publish') || str_ends_with($path, '/media'),
        ));
    }

    /**
     * `impressions`, `plays` and `video_views` were all removed; asking for any of them returns
     * an error that reads like a bug in this application. `views` replaced all three.
     */
    public function test_asks_only_for_metric_names_meta_still_serves(): void
    {
        $this->queueSuccessfulPublish();

        $this->publish();

        $metrics = explode(',', $this->requestedMetrics());

        $this->assertContains('views', $metrics);
        $this->assertContains('saved', $metrics);
        $this->assertSame([], array_intersect(['impressions', 'plays', 'video_views'], $metrics));
    }

    public function test_stores_the_organic_series_from_the_metrics_it_read(): void
    {
        $this->queueSuccessfulPublish();

        $this->publish();

        $this->assertDatabaseHas('experiment_metrics', [
            'experiment_id' => $this->schedule->experiment_id,
            'impressions' => 900,
            'reach' => 700,
        ]);
    }

    /** The `metric` parameter of the one call made against the media insights edge. */
    private function requestedMetrics(): string
    {
        foreach (range(0, $this->transport->requestCount() - 1) as $index) {
            if (str_ends_with($this->transport->path($index), '/insights')) {
                parse_str($this->transport->query($index), $parameters);

                return (string) $parameters['metric'];
            }
        }

        $this->fail('No call was made to the media insights edge.');
    }

    private function publish(): void
    {
        try {
            PublishScheduledContentJob::dispatch((int) $this->account->id, (int) $this->schedule->id);
        } catch (Throwable) {
            // The sync queue calls failed() and then rethrows; the recorded outcome is the subject.
        }
    }

    private function queueSuccessfulPublish(int $quotaUsage = 0, int $quotaTotal = 50): void
    {
        $this->queuePages();
        $this->queueLimit($quotaUsage, $quotaTotal);
        $this->queuePages();
        $this->transport->queue(
            FakeTransport::json(['id' => self::CONTAINER_ID]),
            FakeTransport::json(['id' => self::CONTAINER_ID, 'status_code' => 'FINISHED']),
            FakeTransport::json(['id' => self::MEDIA_ID]),
            FakeTransport::json(['id' => self::MEDIA_ID, 'permalink' => 'https://www.instagram.com/reel/XYZ/']),
        );
        $this->queueMetricsImport();
    }

    /**
     * The sync queue ignores the job's 24-hour delay, so the metrics read that normally happens
     * a day later runs inside the same dispatch and needs its own two answers.
     */
    private function queueMetricsImport(): void
    {
        $this->transport->queue(
            FakeTransport::json([
                'id' => self::MEDIA_ID,
                'permalink' => 'https://www.instagram.com/reel/XYZ/',
                'timestamp' => '2026-09-08T12:00:00+0000',
                'like_count' => 12,
                'comments_count' => 3,
            ]),
            FakeTransport::json(['data' => [
                ['name' => 'views', 'total_value' => ['value' => 900]],
                ['name' => 'reach', 'total_value' => ['value' => 700]],
                ['name' => 'saved', 'total_value' => ['value' => 25]],
                ['name' => 'total_interactions', 'total_value' => ['value' => 40]],
            ]]),
        );
    }

    private function queueTerminalContainer(string $statusCode): void
    {
        $this->queuePages();
        $this->queueLimit();
        $this->queuePages();
        $this->transport->queue(
            FakeTransport::json(['id' => self::CONTAINER_ID]),
            FakeTransport::json([
                'id' => self::CONTAINER_ID,
                'status_code' => $statusCode,
                'status' => "{$statusCode}: the media could not be ingested.",
            ]),
        );
    }

    private function queuePages(): void
    {
        $this->transport->queue(FakeTransport::json(['data' => [[
            'id' => '101000000000001',
            'name' => 'Marca',
            'tasks' => ['MANAGE', 'CREATE_CONTENT'],
            'instagram_business_account' => ['id' => self::IG_USER_ID],
        ]]]));
    }

    private function queueLimit(int $quotaUsage = 0, int $quotaTotal = 50): void
    {
        $this->transport->queue(FakeTransport::json(['data' => [[
            'quota_usage' => $quotaUsage,
            'config' => ['quota_total' => $quotaTotal, 'quota_duration' => 86400],
        ]]]));
    }

    private function reel(): Asset
    {
        return Asset::factory()->ready()->create([
            'account_id' => $this->account->id,
            'type' => AssetType::Reel,
            'mime_type' => 'video/mp4',
            'size_bytes' => 40_000_000,
            'duration_seconds' => 32,
        ]);
    }
}
