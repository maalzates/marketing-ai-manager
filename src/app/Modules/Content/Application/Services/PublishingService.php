<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Services;

use App\Modules\Assets\Application\DTO\AttachToExperimentDTO;
use App\Modules\Assets\Application\Services\AssetService;
use App\Modules\Content\Application\DTO\CalendarQueryDTO;
use App\Modules\Content\Application\DTO\CreateScheduleDTO;
use App\Modules\Content\Application\DTO\PublishRequestDTO;
use App\Modules\Content\Application\DTO\RecordingBatchDTO;
use App\Modules\Content\Application\DTO\ScheduleFilterDTO;
use App\Modules\Content\Application\DTO\UpdateScheduleDTO;
use App\Modules\Content\Domain\Contracts\ChannelProviderInterface;
use App\Modules\Content\Domain\Contracts\ChannelProviderRegistryInterface;
use App\Modules\Content\Domain\Contracts\ContentScheduleRepositoryInterface;
use App\Modules\Content\Domain\Contracts\ContentScriptRepositoryInterface;
use App\Modules\Content\Domain\Enums\ContentFormat;
use App\Modules\Content\Domain\Enums\ScheduleStatus;
use App\Modules\Content\Domain\Exceptions\ContentScheduleNotFoundException;
use App\Modules\Content\Domain\Exceptions\ContentScriptNotFoundException;
use App\Modules\Content\Domain\Exceptions\InstagramApiException;
use App\Modules\Content\Domain\Exceptions\PublishingContainerTimedOutException;
use App\Modules\Content\Domain\Exceptions\ScheduleAssetMissingException;
use App\Modules\Content\Domain\Exceptions\ScriptNotApprovedException;
use App\Modules\Content\Domain\Exceptions\UnsupportedChannelException;
use App\Modules\Content\Infrastructure\Persistence\ContentSchedule;
use App\Modules\Content\Infrastructure\Persistence\ContentScript;
use App\Modules\Experiments\Application\DTO\UpdateExperimentDTO;
use App\Modules\Experiments\Application\Services\ExperimentService;
use App\Modules\Experiments\Domain\Enums\ExperimentPlatform;
use App\Modules\Experiments\Domain\Enums\ProductionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Owns the production line a creator actually works in: record a batch in one sitting, drop the
 * pieces on a calendar, and let each one go out on its own hour.
 */
readonly class PublishingService
{
    private const int MAX_ATTEMPTS = 3;

    private const int RETRY_BACKOFF_MINUTES = 15;

    /** Over quota there is nothing to do but wait: the window is a rolling 24 hours. */
    private const int QUOTA_BACKOFF_MINUTES = 60;

    private const string MANUAL_REMINDER = 'manual_publish_required: this channel has no publishing API; publish the piece by hand and mark it published.';

    public function __construct(
        private ContentScheduleRepositoryInterface $schedules,
        private ContentScriptRepositoryInterface $scripts,
        private ChannelProviderRegistryInterface $channels,
        private PublishableMediaResolver $media,
        private AssetService $assets,
        private ExperimentService $experiments,
    ) {}

    /**
     * @return Collection<int, ContentSchedule>|LengthAwarePaginator<int, ContentSchedule>
     */
    public function forAccount(ScheduleFilterDTO $filters): Collection|LengthAwarePaginator
    {
        return $this->schedules->findAll($filters);
    }

    public function find(int $id, int $accountId): ContentSchedule
    {
        return $this->schedules->findById($id, $accountId) ?? throw ContentScheduleNotFoundException::withId($id);
    }

    /**
     * @return Collection<int, ContentSchedule>
     */
    public function calendar(CalendarQueryDTO $query): Collection
    {
        return $this->schedules->calendar($query);
    }

    public function schedule(CreateScheduleDTO $dto): ContentSchedule
    {
        $platform = $this->experiments->find($dto->experimentId, $dto->accountId)->platform;

        $this->channelFor($platform);

        // A slot with no recording linked yet is still only a script: it holds a date, not a piece.
        return $this->withProductionStatus(
            $this->schedules->create($dto, $platform),
            $dto->assetId === null ? null : ProductionStatus::Scheduled,
        );
    }

    public function update(UpdateScheduleDTO $dto): ContentSchedule
    {
        return $this->withProductionStatus(
            $this->schedules->update($this->find($dto->scheduleId, $dto->accountId), $dto),
            $this->productionStatusFor($dto),
        );
    }

    public function delete(int $id, int $accountId): bool
    {
        return $this->schedules->delete($this->find($id, $accountId));
    }

    /**
     * The batch step: several pieces recorded in one sitting, each linked to the script that
     * asked for it. Linking is what turns a script into something the calendar can place.
     *
     * @return Collection<int, ContentScript>
     */
    public function linkRecordings(RecordingBatchDTO $dto): Collection
    {
        return collect($dto->assetIdByScriptId)
            ->map(fn (int $assetId, int|string $scriptId): ContentScript => $this->linkRecording(
                $dto->accountId,
                (int) $scriptId,
                $assetId,
            ))
            ->values();
    }

    /**
     * Claims every slot whose time has come, so a sweep that overlaps the previous one cannot
     * hand the same piece to two workers.
     *
     * @return Collection<int, ContentSchedule>
     */
    public function claimDue(): Collection
    {
        return $this->schedules
            ->due(CarbonImmutable::now(), self::MAX_ATTEMPTS)
            ->map(fn (ContentSchedule $schedule): ContentSchedule => $this->schedules->markPublishing($schedule));
    }

    public function publish(int $accountId, int $scheduleId): ContentSchedule
    {
        $schedule = $this->find($scheduleId, $accountId);
        $provider = $this->channelFor($schedule->platform);

        if ($schedule->status === ScheduleStatus::Published) {
            return $schedule;
        }

        if (! $provider->supportsPublishing()) {
            return $this->schedules->markFailed($schedule, self::MANUAL_REMINDER);
        }

        return $provider->publishingLimit($accountId)->hasHeadroom()
            ? $this->send($provider, $schedule, $accountId)
            : $this->schedules->reschedule(
                $schedule,
                CarbonImmutable::now()->addMinutes(self::QUOTA_BACKOFF_MINUTES),
                'publishing_quota_exhausted',
            );
    }

    /**
     * Called from the job's failure hook. A container that is merely slow, or a Meta error Meta
     * itself calls transient, goes back on the calendar; anything else is definitive and the
     * piece falls back to a manual publish.
     */
    public function handleFailure(int $accountId, int $scheduleId, Throwable $exception): ContentSchedule
    {
        $schedule = $this->find($scheduleId, $accountId);

        return self::isTransient($exception) && $schedule->attempts < self::MAX_ATTEMPTS
            ? $this->schedules->reschedule(
                $schedule,
                CarbonImmutable::now()->addMinutes(self::RETRY_BACKOFF_MINUTES),
                $exception->getMessage(),
            )
            : $this->withProductionStatus(
                $this->schedules->markFailed($schedule, $exception->getMessage().' '.self::MANUAL_REMINDER),
                ProductionStatus::Failed,
            );
    }

    private function send(ChannelProviderInterface $provider, ContentSchedule $schedule, int $accountId): ContentSchedule
    {
        $script = $this->scripts->findByExperiment((int) $schedule->experiment_id, $accountId);
        // A slot created by hand, outside the planner, has no script to take a format from.
        $format = $script?->format ?? ContentFormat::Reel;

        return $this->withProductionStatus(
            $this->schedules->markPublished($schedule, $provider->publish(new PublishRequestDTO(
                $accountId,
                (int) $schedule->id,
                $format,
                $this->media->urlsFor(
                    $schedule->asset_id ?? throw ScheduleAssetMissingException::withId((int) $schedule->id),
                    $accountId,
                    $provider->mediaSpec($format),
                ),
                self::captionFrom($script),
            ))->externalPostId),
            ProductionStatus::Published,
        );
    }

    private function linkRecording(int $accountId, int $scriptId, int $assetId): ContentScript
    {
        $script = $this->scripts->findById($scriptId, $accountId)
            ?? throw ContentScriptNotFoundException::withId($scriptId);

        $experimentId = $script->experiment_id ?? throw ScriptNotApprovedException::withId($scriptId);

        $this->assets->attachToExperiment(new AttachToExperimentDTO($accountId, $assetId, $experimentId, $script->title));

        $this->experiments->update(self::productionStatusChange($accountId, $experimentId, ProductionStatus::Recorded));

        return $script;
    }

    private function withProductionStatus(ContentSchedule $schedule, ?ProductionStatus $status): ContentSchedule
    {
        if ($status !== null) {
            $this->experiments->update(self::productionStatusChange(
                (int) $schedule->account_id,
                (int) $schedule->experiment_id,
                $status,
            ));
        }

        return $schedule;
    }

    private function productionStatusFor(UpdateScheduleDTO $dto): ?ProductionStatus
    {
        return match (true) {
            $dto->status === ScheduleStatus::Published => ProductionStatus::Published,
            $dto->status === ScheduleStatus::Failed => ProductionStatus::Failed,
            $dto->scheduledAt !== null || $dto->assetId !== null => ProductionStatus::Scheduled,
            default => null,
        };
    }

    private function channelFor(ExperimentPlatform $platform): ChannelProviderInterface
    {
        return $this->channels->for($platform) ?? throw UnsupportedChannelException::forPlatform($platform);
    }

    private static function productionStatusChange(int $accountId, int $experimentId, ProductionStatus $status): UpdateExperimentDTO
    {
        return new UpdateExperimentDTO(
            $accountId,
            $experimentId,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $status,
        );
    }

    private static function captionFrom(?ContentScript $script): string
    {
        return $script === null ? '' : trim($script->hook."\n\n".$script->cta);
    }

    private static function isTransient(Throwable $exception): bool
    {
        return $exception instanceof PublishingContainerTimedOutException
            || ($exception instanceof InstagramApiException && $exception->isTransient());
    }
}
