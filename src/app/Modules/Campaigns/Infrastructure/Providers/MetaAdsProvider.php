<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Infrastructure\Providers;

use App\Modules\Campaigns\Domain\Contracts\AdsProviderInterface;
use App\Modules\Campaigns\Domain\Enums\AdMediaKind;
use App\Modules\Campaigns\Domain\Enums\CampaignObjective;
use App\Modules\Campaigns\Domain\Enums\LearningStage;
use App\Modules\Campaigns\Domain\ValueObjects\AdMedia;
use App\Modules\Campaigns\Domain\ValueObjects\AdsAccountTarget;
use App\Modules\Campaigns\Domain\ValueObjects\AdSetSpec;
use App\Modules\Campaigns\Domain\ValueObjects\AdSpec;
use App\Modules\Campaigns\Domain\ValueObjects\BudgetPlan;
use App\Modules\Campaigns\Domain\ValueObjects\CampaignSpec;
use App\Modules\Campaigns\Domain\ValueObjects\CreativeSpec;
use App\Modules\Campaigns\Domain\ValueObjects\DailyMetrics;
use App\Modules\Campaigns\Infrastructure\Clients\MetaAdsClient;
use App\Modules\Campaigns\Infrastructure\Clients\MetaAdsClientFactory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Translates the platform-neutral specs into Meta's wire format. Everything Meta-shaped
 * stops here: enum names, minor units, the string-typed numbers it answers with.
 */
readonly class MetaAdsProvider implements AdsProviderInterface
{
    /** Nothing this app creates goes live on creation: activation is a separate, explicit human action. */
    private const string CREATION_STATUS = 'PAUSED';

    private const string PAUSED_STATUS = 'PAUSED';

    private const string BILLING_EVENT = 'IMPRESSIONS';

    private const string BID_STRATEGY = 'LOWEST_COST_WITHOUT_CAP';

    private const string INSIGHTS_LEVEL = 'campaign';

    /** Removed from Instagram placements in v26.0: sending it is now an error, not a no-op. */
    private const string REMOVED_INSTAGRAM_POSITION = 'explore';

    /**
     * Creative enhancements Meta may apply on its own. They are opted out one by one
     * because `standard_enhancements` stopped being a bundle around v22.0, and because a
     * brand asset that Meta re-crops, re-scores or re-writes is no longer the piece the
     * user approved.
     *
     * @var list<string>
     */
    private const array AUTOMATIC_ENHANCEMENTS = [
        'standard_enhancements', 'image_touchups', 'image_templates', 'image_animation',
        'image_background_gen', 'add_text_overlay', 'text_optimizations', 'description_automation',
        'generate_cta', 'video_highlights', 'video_to_image', 'multi_photo_to_video',
        'music_generation', 'adapt_to_placement', 'media_type_automation', 'replace_media_text',
        'text_translation', 'profile_card',
    ];

    /**
     * Action types that count as a conversion for the experiment's daily series.
     *
     * @var list<string>
     */
    private const array CONVERSION_ACTIONS = [
        'purchase', 'lead', 'complete_registration',
        'offsite_conversion.fb_pixel_purchase', 'offsite_conversion.fb_pixel_lead',
        'offsite_conversion.fb_pixel_complete_registration', 'onsite_conversion.lead_grouped',
    ];

    private const string ENGAGEMENT_ACTION = 'post_engagement';

    private const string VIDEO_VIEW_ACTION = 'video_view';

    public function __construct(private MetaAdsClientFactory $clients) {}

    public function createCampaign(AdsAccountTarget $target, CampaignSpec $spec): string
    {
        return $this->clients->forAccount($target)->createCampaign([
            'name' => $spec->name,
            'objective' => $spec->objective->value,
            'status' => self::CREATION_STATUS,
            // Required on every create, even empty: Meta rejects the call without the key.
            'special_ad_categories' => $spec->specialAdCategories,
            'bid_strategy' => self::BID_STRATEGY,
        ]);
    }

    public function createAdSet(AdsAccountTarget $target, AdSetSpec $spec): string
    {
        $client = $this->clients->forAccount($target);

        return $client->createAdSet(array_filter([
            'name' => $spec->name,
            'campaign_id' => $spec->externalCampaignId,
            'optimization_goal' => self::optimisationGoal($spec->objective),
            'billing_event' => self::BILLING_EVENT,
            'bid_strategy' => self::BID_STRATEGY,
            'status' => self::CREATION_STATUS,
            'targeting' => self::targeting($spec->targeting),
            'start_time' => $spec->startsAt->toIso8601String(),
            'end_time' => $spec->endsAt->toIso8601String(),
            'destination_type' => self::destinationType($spec->objective),
            'promoted_object' => $spec->promotedObject,
            ...self::budget($client, $spec->budget),
        ], self::isPresent(...)));
    }

    public function createAdCreative(AdsAccountTarget $target, CreativeSpec $spec): string
    {
        return $this->clients->forAccount($target)->createAdCreative([
            'name' => $spec->name,
            'object_story_spec' => array_filter([
                'page_id' => $spec->pageId,
                'instagram_user_id' => $spec->instagramUserId,
                ...self::story($spec),
            ], self::isPresent(...)),
            'degrees_of_freedom_spec' => [
                'creative_features_spec' => self::creativeFeatures($spec->automaticEnhancements),
            ],
        ]);
    }

    public function createAd(AdsAccountTarget $target, AdSpec $spec): string
    {
        return $this->clients->forAccount($target)->createAd(array_filter([
            'name' => $spec->name,
            'adset_id' => $spec->externalAdSetId,
            'creative' => ['creative_id' => $spec->externalCreativeId],
            'status' => self::CREATION_STATUS,
            // Omitting it fails with code 100 on any ad set optimising offsite conversions.
            'conversion_domain' => $spec->conversionDomain,
        ], self::isPresent(...)));
    }

    public function updateBudget(AdsAccountTarget $target, string $externalAdSetId, BudgetPlan $budget): void
    {
        $client = $this->clients->forAccount($target);

        $client->updateNode($externalAdSetId, self::budget($client, $budget));
    }

    public function pause(AdsAccountTarget $target, string $externalCampaignId): void
    {
        $this->clients->forAccount($target)->updateNode($externalCampaignId, ['status' => self::PAUSED_STATUS]);
    }

    /**
     * @return Collection<int, DailyMetrics>
     */
    public function insights(
        AdsAccountTarget $target,
        string $externalCampaignId,
        CarbonImmutable $since,
        CarbonImmutable $until,
    ): Collection {
        return collect($this->clients->forAccount($target)->dailyInsights(
            $externalCampaignId,
            $since,
            $until,
            self::INSIGHTS_LEVEL,
        ))->map(self::toDailyMetrics(...))->values();
    }

    public function learningStage(AdsAccountTarget $target, string $externalAdSetId): ?LearningStage
    {
        return LearningStage::tryFrom(
            (string) $this->clients->forAccount($target)->learningStage($externalAdSetId)
        );
    }

    /**
     * @return array<string, int>
     */
    private static function budget(MetaAdsClient $client, BudgetPlan $budget): array
    {
        return array_filter([
            'daily_budget' => $budget->daily === null ? null : $client->budgetMinorUnits($budget->daily),
            'lifetime_budget' => $budget->lifetime === null ? null : $client->budgetMinorUnits($budget->lifetime),
        ], self::isPresent(...));
    }

    private static function optimisationGoal(CampaignObjective $objective): string
    {
        return match ($objective) {
            CampaignObjective::Awareness => 'REACH',
            CampaignObjective::Traffic => 'LINK_CLICKS',
            CampaignObjective::Engagement => 'POST_ENGAGEMENT',
            CampaignObjective::Leads => 'LEAD_GENERATION',
            CampaignObjective::AppPromotion => 'APP_INSTALLS',
            CampaignObjective::Sales => 'OFFSITE_CONVERSIONS',
        };
    }

    private static function destinationType(CampaignObjective $objective): ?string
    {
        return match ($objective) {
            CampaignObjective::Traffic, CampaignObjective::Sales => 'WEBSITE',
            CampaignObjective::Engagement => 'ON_POST',
            CampaignObjective::Leads => 'ON_AD',
            CampaignObjective::AppPromotion => 'APP',
            CampaignObjective::Awareness => null,
        };
    }

    /**
     * @param  array<string, mixed>  $targeting
     * @return array<string, mixed>
     */
    private static function targeting(array $targeting): array
    {
        return array_filter([
            ...$targeting,
            'instagram_positions' => array_values(array_diff(
                (array) ($targeting['instagram_positions'] ?? []),
                [self::REMOVED_INSTAGRAM_POSITION],
            )),
            // v26.0 makes this mandatory for special ad categories with relaxable targeting,
            // and silently opt-in elsewhere — so it is always stated, never inferred.
            'targeting_automation' => [
                'advantage_audience' => (int) ($targeting['targeting_automation']['advantage_audience'] ?? 0),
            ],
        ], self::isPresent(...));
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function creativeFeatures(bool $enabled): array
    {
        return collect(self::AUTOMATIC_ENHANCEMENTS)
            ->mapWithKeys(fn (string $feature): array => [
                $feature => ['enroll_status' => $enabled ? 'OPT_IN' : 'OPT_OUT'],
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function story(CreativeSpec $spec): array
    {
        return match (true) {
            self::firstVideo($spec->media) instanceof AdMedia => ['video_data' => self::videoData($spec)],
            count($spec->media) > 1 => ['link_data' => self::carouselData($spec)],
            default => ['link_data' => self::linkData($spec, self::firstImageHash($spec->media))],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function videoData(CreativeSpec $spec): array
    {
        return array_filter([
            'video_id' => self::firstVideo($spec->media)?->externalId,
            'image_hash' => self::firstVideo($spec->media)?->thumbnailExternalId,
            'message' => $spec->message,
            'title' => $spec->headline,
            'call_to_action' => self::callToAction($spec),
        ], self::isPresent(...));
    }

    /**
     * @return array<string, mixed>
     */
    private static function linkData(CreativeSpec $spec, ?string $imageHash): array
    {
        return array_filter([
            'link' => $spec->link,
            'message' => $spec->message,
            'name' => $spec->headline,
            'image_hash' => $imageHash,
            'call_to_action' => self::callToAction($spec),
        ], self::isPresent(...));
    }

    /**
     * @return array<string, mixed>
     */
    private static function carouselData(CreativeSpec $spec): array
    {
        return [
            ...self::linkData($spec, null),
            'child_attachments' => collect($spec->media)
                ->map(fn (AdMedia $media): array => array_filter([
                    'link' => $spec->link,
                    'image_hash' => $media->kind === AdMediaKind::Image ? $media->externalId : null,
                    'video_id' => $media->kind === AdMediaKind::Video ? $media->externalId : null,
                ], self::isPresent(...)))
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function callToAction(CreativeSpec $spec): ?array
    {
        return $spec->callToAction === null
            ? null
            : array_filter([
                'type' => $spec->callToAction,
                'value' => $spec->link === null ? null : ['link' => $spec->link],
            ], self::isPresent(...));
    }

    /**
     * @param  list<AdMedia>  $media
     */
    private static function firstVideo(array $media): ?AdMedia
    {
        return collect($media)->first(fn (AdMedia $piece): bool => $piece->kind === AdMediaKind::Video);
    }

    /**
     * @param  list<AdMedia>  $media
     */
    private static function firstImageHash(array $media): ?string
    {
        return collect($media)->first(fn (AdMedia $piece): bool => $piece->kind === AdMediaKind::Image)?->externalId;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function toDailyMetrics(array $row): DailyMetrics
    {
        return new DailyMetrics(
            CarbonImmutable::parse((string) ($row['date_start'] ?? 'today'))->startOfDay(),
            self::decimal($row, 'spend'),
            (int) self::decimal($row, 'impressions'),
            (int) self::decimal($row, 'reach'),
            (int) self::decimal($row, 'clicks'),
            self::decimal($row, 'ctr'),
            self::decimal($row, 'cpm'),
            self::decimal($row, 'cpc'),
            self::conversions($row),
            self::costPerConversion($row),
            self::decimal($row, 'frequency'),
            self::videoViews($row),
            self::actionValue($row, 'actions', [self::ENGAGEMENT_ACTION]),
            $row,
        );
    }

    /**
     * `video_play_actions` is the dedicated field; `actions.video_view` is the same number
     * arriving through the generic list, so counting both would double every play.
     *
     * @param  array<string, mixed>  $row
     */
    private static function videoViews(array $row): int
    {
        return isset($row['video_play_actions'])
            ? self::actionValue($row, 'video_play_actions', [])
            : self::actionValue($row, 'actions', [self::VIDEO_VIEW_ACTION]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function conversions(array $row): int
    {
        return self::actionValue($row, 'actions', self::CONVERSION_ACTIONS);
    }

    /**
     * Derived instead of read from `cost_per_action_type`: that field returns one entry per
     * attribution window and picking among them silently changes what CPA means.
     *
     * @param  array<string, mixed>  $row
     */
    private static function costPerConversion(array $row): ?float
    {
        return self::conversions($row) > 0
            ? self::decimal($row, 'spend') / self::conversions($row)
            : null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $actionTypes
     */
    private static function actionValue(array $row, string $field, array $actionTypes): int
    {
        return (int) collect((array) ($row[$field] ?? []))
            ->filter(fn (mixed $action): bool => $actionTypes === []
                || in_array($action['action_type'] ?? null, $actionTypes, true))
            ->sum(fn (mixed $action): float => (float) ($action['value'] ?? 0));
    }

    /**
     * Every numeric metric arrives as a string, including the ones that look like counters.
     *
     * @param  array<string, mixed>  $row
     */
    private static function decimal(array $row, string $field): float
    {
        return (float) ($row[$field] ?? 0);
    }

    private static function isPresent(mixed $value): bool
    {
        return $value !== null && $value !== [];
    }
}
