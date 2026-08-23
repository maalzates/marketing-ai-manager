<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Services;

use App\Modules\Audit\Application\DTO\RecordActionDTO;
use App\Modules\Audit\Application\Services\ActionLogService;
use App\Modules\Audit\Domain\Enums\ActionOrigin;
use App\Modules\Campaigns\Application\DTO\CampaignFilterDTO;
use App\Modules\Campaigns\Application\DTO\LaunchCampaignDTO;
use App\Modules\Campaigns\Application\DTO\PauseCampaignDTO;
use App\Modules\Campaigns\Application\DTO\SyncCampaignMetricsDTO;
use App\Modules\Campaigns\Application\DTO\UpdateCampaignBudgetDTO;
use App\Modules\Campaigns\Application\Jobs\SyncCampaignMetricsJob;
use App\Modules\Campaigns\Domain\Contracts\AdsProviderInterface;
use App\Modules\Campaigns\Domain\Contracts\CampaignRepositoryInterface;
use App\Modules\Campaigns\Domain\Enums\CampaignObjective;
use App\Modules\Campaigns\Domain\Enums\CampaignStatus;
use App\Modules\Campaigns\Domain\Exceptions\CampaignAlreadyLaunchedException;
use App\Modules\Campaigns\Domain\Exceptions\CampaignBudgetExceedsCapException;
use App\Modules\Campaigns\Domain\Exceptions\CampaignNotFoundException;
use App\Modules\Campaigns\Domain\Exceptions\CampaignNotOnProviderException;
use App\Modules\Campaigns\Domain\Exceptions\CampaignWithoutReadyAssetsException;
use App\Modules\Campaigns\Domain\ValueObjects\AdsAccountTarget;
use App\Modules\Campaigns\Domain\ValueObjects\AdSetSpec;
use App\Modules\Campaigns\Domain\ValueObjects\AdSpec;
use App\Modules\Campaigns\Domain\ValueObjects\CampaignLaunchResult;
use App\Modules\Campaigns\Domain\ValueObjects\CampaignSpec;
use App\Modules\Campaigns\Domain\ValueObjects\CreativeSpec;
use App\Modules\Campaigns\Domain\ValueObjects\MissingAsset;
use App\Modules\Campaigns\Infrastructure\Persistence\Campaign;
use App\Modules\Experiments\Application\Services\ExperimentService;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use Illuminate\Support\Collection;

/**
 * The one place a paid campaign is created, re-budgeted or stopped. There is no path here
 * from a chat tool: either a human calls it over HTTP, or the proposal-acceptance door
 * calls it after a human accepted. The invariants live here for exactly that reason —
 * a door that could skip them would make them optional.
 */
readonly class CampaignService
{
    public function __construct(
        private CampaignRepositoryInterface $repository,
        private AdsProviderInterface $provider,
        private AdsTargetResolver $targets,
        private CampaignAssetGate $assets,
        private CampaignBudgetGuard $budgets,
        private ExperimentService $experiments,
        private ActionLogService $actionLog,
    ) {}

    /**
     * @return Collection<int, Campaign>
     */
    public function forAccount(CampaignFilterDTO $filters): Collection
    {
        return $this->repository->forAccount($filters);
    }

    /**
     * The scheduler's fan-out: one per-experiment job per campaign that still has delivery to
     * fetch. Nothing here talks to the provider — an account whose experiments have all
     * finished enqueues jobs that find them not running and return before any request is
     * built, so a quiet account costs zero provider calls and zero of the user's money.
     */
    public function dispatchMetricsSync(): void
    {
        $this->repository->stillAccruingMetrics()->each(
            fn (Campaign $campaign) => SyncCampaignMetricsJob::dispatch(new SyncCampaignMetricsDTO(
                (int) $campaign->account_id,
                (int) $campaign->experiment_id,
            )),
        );
    }

    public function forExperiment(int $accountId, int $experimentId): Campaign
    {
        return $this->repository->findForExperiment($accountId, $experimentId)
            ?? throw CampaignNotFoundException::forExperiment($experimentId);
    }

    /**
     * What a proposal is still waiting for. Empty means the campaign may be approved.
     *
     * @param  list<int>  $assetIds
     * @return Collection<int, MissingAsset>
     */
    public function missingAssets(int $accountId, array $assetIds): Collection
    {
        return $this->assets->missing($accountId, $assetIds);
    }

    /**
     * Every invariant that can be judged without calling the platform, in one place so the
     * proposal-acceptance door can reject a bad launch synchronously — with a real 422 — and
     * still hand the slow provider work to the queue. Returns the experiment it validated
     * against so `launch()` does not read it a second time.
     *
     * @throws CampaignAlreadyLaunchedException|CampaignBudgetExceedsCapException|CampaignWithoutReadyAssetsException
     */
    public function launchable(LaunchCampaignDTO $dto): Experiment
    {
        $experiment = $this->experiments->find($dto->experimentId, $dto->accountId);

        $this->assertNotAlreadyLaunched($dto->accountId, $dto->experimentId);
        $this->budgets->assertWithinCaps($experiment, $dto->budget);
        $this->assets->assertReady($dto->accountId, $dto->experimentId, $dto->assetIds);

        return $experiment;
    }

    /**
     * Runs on the queue, never in a request: uploading an image to Meta means fetching the
     * piece from this application's own signed media route, and a web worker calling its own
     * php-fpm pool can exhaust it. Dispatch LaunchCampaignJob instead of calling this inline.
     */
    public function launch(LaunchCampaignDTO $dto): CampaignLaunchResult
    {
        $experiment = $this->launchable($dto);
        $target = $this->targets->forAccount($dto->accountId);
        $campaign = $this->createOnProvider($target, $dto, $experiment);

        $this->record($dto->accountId, $dto->userId, 'campaign.launched', $campaign, $dto->origin, [
            'external_campaign_id' => $campaign->external_campaign_id,
            'asset_ids' => $dto->assetIds,
        ]);

        return new CampaignLaunchResult($campaign, $this->budgets->warningsFor($experiment, $dto->budget));
    }

    public function pause(PauseCampaignDTO $dto): Campaign
    {
        $campaign = $this->onProvider($dto->accountId, $dto->experimentId);

        $this->provider->pause(
            $this->targets->forAccount($dto->accountId),
            (string) $campaign->external_campaign_id,
        );

        $this->record($dto->accountId, $dto->userId, 'campaign.paused', $campaign, $dto->origin, [
            'reason' => $dto->reason,
        ]);

        return $this->repository->setStatus($campaign, CampaignStatus::Paused);
    }

    public function updateBudget(UpdateCampaignBudgetDTO $dto): Campaign
    {
        $campaign = $this->onProvider($dto->accountId, $dto->experimentId);

        $this->budgets->assertWithinCaps(
            $this->experiments->find($dto->experimentId, $dto->accountId),
            $dto->budget,
        );

        $this->provider->updateBudget(
            $this->targets->forAccount($dto->accountId),
            (string) $campaign->external_adset_id,
            $dto->budget,
        );

        $this->record($dto->accountId, $dto->userId, 'campaign.budget_updated', $campaign, $dto->origin, [
            'daily_budget' => $dto->budget->daily,
            'lifetime_budget' => $dto->budget->lifetime,
        ]);

        return $this->repository->setBudget($campaign, $dto->budget);
    }

    /**
     * Each identifier is persisted before the next call is made: Meta deduplicates
     * creatives but not campaigns, ad sets or ads, so a launch that dies halfway has to be
     * resumable rather than replayable.
     */
    private function createOnProvider(
        AdsAccountTarget $target,
        LaunchCampaignDTO $dto,
        Experiment $experiment,
    ): Campaign {
        $campaign = $this->repository->startLaunch($dto, $target->sandbox);

        $campaign = $this->repository->recordExternalIds($campaign, [
            'external_campaign_id' => $campaign->external_campaign_id
                ?? $this->provider->createCampaign($target, new CampaignSpec(
                    self::nameFor($experiment, 'campaña'),
                    $dto->objective,
                    $dto->specialAdCategories,
                )),
        ]);

        $campaign = $this->repository->recordExternalIds($campaign, [
            'external_adset_id' => $campaign->external_adset_id
                ?? $this->provider->createAdSet($target, new AdSetSpec(
                    self::nameFor($experiment, 'ad set'),
                    (string) $campaign->external_campaign_id,
                    $dto->objective,
                    $dto->budget,
                    $dto->targeting,
                    $experiment->starts_at,
                    $experiment->ends_at,
                    self::promotedObject($dto),
                )),
        ]);

        $campaign = $this->repository->recordExternalIds($campaign, [
            'external_ad_id' => $campaign->external_ad_id ?? $this->provider->createAd($target, new AdSpec(
                self::nameFor($experiment, 'anuncio'),
                (string) $campaign->external_adset_id,
                $this->creativeFor($target, $dto, $experiment),
                $dto->conversionDomain,
            )),
        ]);

        return $this->repository->setStatus($campaign, CampaignStatus::Paused);
    }

    private function creativeFor(
        AdsAccountTarget $target,
        LaunchCampaignDTO $dto,
        Experiment $experiment,
    ): string {
        return $this->provider->createAdCreative($target, new CreativeSpec(
            self::nameFor($experiment, 'creativo'),
            $dto->pageId,
            $dto->instagramUserId,
            $dto->message,
            $dto->headline,
            $dto->link,
            $dto->callToAction,
            $this->assets->uploadedMedia($dto->accountId, $dto->experimentId, $dto->assetIds)->all(),
            $dto->advantagePlusCreative,
        ));
    }

    private function assertNotAlreadyLaunched(int $accountId, int $experimentId): void
    {
        $campaign = $this->repository->findForExperiment($accountId, $experimentId);

        if ($campaign?->external_ad_id !== null) {
            throw CampaignAlreadyLaunchedException::forExperiment(
                $experimentId,
                (string) $campaign->external_campaign_id,
            );
        }
    }

    private function onProvider(int $accountId, int $experimentId): Campaign
    {
        $campaign = $this->forExperiment($accountId, $experimentId);

        return $campaign->external_campaign_id === null
            ? throw CampaignNotOnProviderException::forExperiment($experimentId)
            : $campaign;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function record(
        int $accountId,
        ?int $userId,
        string $action,
        Campaign $campaign,
        ActionOrigin $origin,
        array $payload,
    ): void {
        $this->actionLog->record(new RecordActionDTO(
            $accountId,
            $userId,
            $action,
            $origin,
            // The sandbox flag rides on every entry: the audit trail has to say which world
            // an operation happened in, not only that it happened.
            ['sandbox' => $campaign->sandbox] + $payload,
            'campaign',
            (int) $campaign->id,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private static function promotedObject(LaunchCampaignDTO $dto): array
    {
        return match (true) {
            $dto->promotedObject !== [] => $dto->promotedObject,
            $dto->objective === CampaignObjective::Leads => ['page_id' => $dto->pageId],
            default => [],
        };
    }

    private static function nameFor(Experiment $experiment, string $piece): string
    {
        return sprintf('%s — %s (%s)', $experiment->code, $experiment->title, $piece);
    }
}
