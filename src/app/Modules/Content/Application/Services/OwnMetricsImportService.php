<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Services;

use App\Modules\Content\Application\DTO\ChannelMetricsDTO;
use App\Modules\Content\Domain\Contracts\AudienceSnapshotRepositoryInterface;
use App\Modules\Content\Domain\Contracts\ChannelProviderInterface;
use App\Modules\Content\Domain\Contracts\ChannelProviderRegistryInterface;
use App\Modules\Content\Domain\Contracts\ContentScheduleRepositoryInterface;
use App\Modules\Content\Domain\Contracts\ContentScriptRepositoryInterface;
use App\Modules\Content\Domain\Enums\ContentFormat;
use App\Modules\Content\Domain\Exceptions\ContentScheduleNotFoundException;
use App\Modules\Content\Domain\Exceptions\UnsupportedChannelException;
use App\Modules\Content\Infrastructure\Persistence\ChannelAudienceSnapshot;
use App\Modules\Content\Infrastructure\Persistence\ContentSchedule;
use App\Modules\Experiments\Application\DTO\RecordMetricsDTO;
use App\Modules\Experiments\Application\Services\ExperimentService;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use App\Modules\Integrations\Application\Services\IntegrationService;
use App\Modules\Integrations\Domain\Enums\IntegrationStatus;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Feeds what a published piece actually did back into the experiment that predicted it.
 *
 * Only current metric names reach the channel: `impressions`, `plays` and `video_views` are dead
 * and `views` replaced all three, so the `impressions` column of an organic experiment carries
 * `views`. Followers are not readable as an insight metric any more either — they are snapshotted
 * daily from the profile field instead.
 */
readonly class OwnMetricsImportService
{
    public function __construct(
        private ContentScheduleRepositoryInterface $schedules,
        private ContentScriptRepositoryInterface $scripts,
        private ChannelProviderRegistryInterface $channels,
        private ExperimentService $experiments,
        private AudienceSnapshotRepositoryInterface $snapshots,
        private IntegrationService $integrations,
    ) {}

    public function importFor(int $accountId, int $scheduleId): Experiment
    {
        return $this->import(
            $this->schedules->findById($scheduleId, $accountId)
                ?? throw ContentScheduleNotFoundException::withId($scheduleId),
            $accountId,
        );
    }

    /**
     * The door for callers that hold an experiment rather than a slot — the guardián syncing an
     * organic experiment before it looks for anomalies. Null means the piece is not out yet,
     * which is a normal state and not a failure.
     */
    public function importForExperiment(int $accountId, int $experimentId): ?Experiment
    {
        $schedule = $this->schedules->publishedForExperiment($experimentId, $accountId);

        return $schedule === null ? null : $this->import($schedule, $accountId);
    }

    private function import(ContentSchedule $schedule, int $accountId): Experiment
    {
        $format = $this->formatOf($schedule, $accountId);

        return $this->experiments->recordMetrics($this->toRecordMetrics(
            $schedule,
            $format,
            $this->channelFor($schedule)->metrics($accountId, (string) $schedule->external_post_id, $format),
        ));
    }

    /**
     * Instagram's numbers move for up to 48 hours after a post, so a piece is re-read for a
     * while rather than measured once.
     *
     * @return Collection<int, ContentSchedule>
     */
    public function recentlyPublished(CarbonImmutable $since): Collection
    {
        return $this->schedules->publishedSince($since);
    }

    /** @return Collection<int, int> */
    public function accountsWithPublishedContent(): Collection
    {
        return $this->schedules->accountIdsWithPublishedContent();
    }

    /**
     * @return Collection<int, ChannelAudienceSnapshot>
     */
    public function snapshotAudience(int $accountId): Collection
    {
        $connected = $this->integrations->list($accountId)
            ->filter(fn (Integration $integration): bool => $integration->status === IntegrationStatus::CONNECTED)
            ->map(fn (Integration $integration) => $integration->provider);

        return $this->channels
            ->all()
            ->filter(fn (ChannelProviderInterface $provider): bool => $connected->contains($provider->credentialProvider()))
            ->map(fn (ChannelProviderInterface $provider) => $this->snapshots->record($provider->audienceSnapshot($accountId)))
            ->values();
    }

    private function toRecordMetrics(ContentSchedule $schedule, ContentFormat $format, ChannelMetricsDTO $metrics): RecordMetricsDTO
    {
        return new RecordMetricsDTO(
            (int) $schedule->account_id,
            (int) $schedule->experiment_id,
            $schedule->published_at ?? $metrics->date,
            0.0,
            $metrics->views,
            $metrics->reach,
            0,
            0.0,
            0.0,
            0.0,
            0,
            null,
            null,
            $format->isVideo() ? $metrics->views : 0,
            $metrics->totalInteractions,
            $metrics->raw,
        );
    }

    private function formatOf(ContentSchedule $schedule, int $accountId): ContentFormat
    {
        return $this->scripts->findByExperiment((int) $schedule->experiment_id, $accountId)?->format
            ?? ContentFormat::Reel;
    }

    private function channelFor(ContentSchedule $schedule): ChannelProviderInterface
    {
        return $this->channels->for($schedule->platform)
            ?? throw UnsupportedChannelException::forPlatform($schedule->platform);
    }
}
