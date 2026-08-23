<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Services;

use App\Modules\Campaigns\Application\DTO\SyncCampaignMetricsDTO;
use App\Modules\Campaigns\Domain\Contracts\AdsProviderInterface;
use App\Modules\Campaigns\Domain\Contracts\CampaignRepositoryInterface;
use App\Modules\Campaigns\Domain\Enums\LearningStage;
use App\Modules\Campaigns\Domain\ValueObjects\AdsAccountTarget;
use App\Modules\Campaigns\Domain\ValueObjects\DailyMetrics;
use App\Modules\Campaigns\Infrastructure\Persistence\Campaign;
use App\Modules\Experiments\Application\DTO\RecordMetricsDTO;
use App\Modules\Experiments\Application\Services\ExperimentService;
use App\Modules\Experiments\Domain\Enums\ExperimentStatus;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use Carbon\CarbonImmutable;

/**
 * Pulls the daily series and hands each day to the Experiments module, which upserts it by
 * date — re-syncing a day corrects it instead of duplicating it, so the job is safe to run
 * as often as the schedule likes.
 */
readonly class CampaignMetricsSyncService
{
    /** Meta caps `time_increment=1` at 90 days, and one row per day is what the window means. */
    private const int MAXIMUM_WINDOW_DAYS = 89;

    public function __construct(
        private CampaignRepositoryInterface $repository,
        private AdsProviderInterface $provider,
        private AdsTargetResolver $targets,
        private ExperimentService $experiments,
    ) {}

    /**
     * Null means there was nothing to sync and no call was made — the experiment is not
     * running, or it is organic and has no campaign at all. The guardián sweeps every active
     * experiment of a strategy, so «nothing to sync» has to be an answer rather than an error.
     */
    public function sync(SyncCampaignMetricsDTO $dto): ?Campaign
    {
        $experiment = $this->experiments->find($dto->experimentId, $dto->accountId);
        $campaign = $this->repository->findForExperiment($dto->accountId, $dto->experimentId);

        if ($experiment->status !== ExperimentStatus::Running || $campaign?->external_campaign_id === null) {
            return null;
        }

        $target = $this->targets->forAccount($dto->accountId);

        $this->provider
            ->insights(
                $target,
                $campaign->external_campaign_id,
                self::since($dto, $experiment),
                self::until($dto, $experiment),
            )
            ->each(fn (DailyMetrics $day) => $this->experiments->recordMetrics(
                self::toRecordMetrics($dto, $day),
            ));

        return $this->repository->recordSync($campaign, $this->learningStage($target, $campaign), CarbonImmutable::now());
    }

    private function learningStage(AdsAccountTarget $target, Campaign $campaign): ?LearningStage
    {
        return $campaign->external_adset_id === null
            ? null
            : $this->provider->learningStage($target, $campaign->external_adset_id);
    }

    private static function toRecordMetrics(SyncCampaignMetricsDTO $dto, DailyMetrics $day): RecordMetricsDTO
    {
        return new RecordMetricsDTO(
            $dto->accountId,
            $dto->experimentId,
            $day->date,
            $day->spend,
            $day->impressions,
            $day->reach,
            $day->clicks,
            $day->ctr,
            $day->cpm,
            $day->cpc,
            $day->conversions,
            $day->cpa,
            $day->frequency,
            $day->videoViews,
            $day->engagement,
            $day->raw,
        );
    }

    private static function since(SyncCampaignMetricsDTO $dto, Experiment $experiment): CarbonImmutable
    {
        return ($dto->since ?? $experiment->starts_at)
            ->max(self::until($dto, $experiment)->subDays(self::MAXIMUM_WINDOW_DAYS));
    }

    private static function until(SyncCampaignMetricsDTO $dto, Experiment $experiment): CarbonImmutable
    {
        return ($dto->until ?? CarbonImmutable::now())->min($experiment->ends_at);
    }
}
