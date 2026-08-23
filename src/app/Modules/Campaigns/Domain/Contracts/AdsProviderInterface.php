<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Domain\Contracts;

use App\Modules\Campaigns\Domain\Enums\LearningStage;
use App\Modules\Campaigns\Domain\ValueObjects\AdsAccountTarget;
use App\Modules\Campaigns\Domain\ValueObjects\AdSetSpec;
use App\Modules\Campaigns\Domain\ValueObjects\AdSpec;
use App\Modules\Campaigns\Domain\ValueObjects\BudgetPlan;
use App\Modules\Campaigns\Domain\ValueObjects\CampaignSpec;
use App\Modules\Campaigns\Domain\ValueObjects\CreativeSpec;
use App\Modules\Campaigns\Domain\ValueObjects\DailyMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The seam between this module and whichever ads platform an experiment runs on. Every
 * method returns the platform's own identifier as an opaque string: they are indexed
 * columns, never keys, because platforms rotate them.
 */
interface AdsProviderInterface
{
    public function createCampaign(AdsAccountTarget $target, CampaignSpec $spec): string;

    public function createAdSet(AdsAccountTarget $target, AdSetSpec $spec): string;

    public function createAdCreative(AdsAccountTarget $target, CreativeSpec $spec): string;

    public function createAd(AdsAccountTarget $target, AdSpec $spec): string;

    public function updateBudget(AdsAccountTarget $target, string $externalAdSetId, BudgetPlan $budget): void;

    public function pause(AdsAccountTarget $target, string $externalCampaignId): void;

    /**
     * One row per day between the two dates, both inclusive.
     *
     * @return Collection<int, DailyMetrics>
     */
    public function insights(
        AdsAccountTarget $target,
        string $externalCampaignId,
        CarbonImmutable $since,
        CarbonImmutable $until,
    ): Collection;

    /** Null when the ad set has never delivered and the platform reports no stage at all. */
    public function learningStage(AdsAccountTarget $target, string $externalAdSetId): ?LearningStage;
}
