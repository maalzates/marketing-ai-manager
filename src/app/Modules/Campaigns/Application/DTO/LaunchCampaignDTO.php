<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\DTO;

use App\Modules\Audit\Domain\Enums\ActionOrigin;
use App\Modules\Campaigns\Domain\Enums\CampaignObjective;
use App\Modules\Campaigns\Domain\ValueObjects\BudgetPlan;

readonly class LaunchCampaignDTO
{
    /**
     * @param  list<int>  $assetIds
     * @param  array<string, mixed>  $targeting
     * @param  list<string>  $specialAdCategories
     * @param  array<string, mixed>  $promotedObject
     * @param  int|null  $launchReference  echoed back to CampaignLaunchObserverInterface when the launch settles
     */
    public function __construct(
        public int $accountId,
        public int $experimentId,
        public CampaignObjective $objective,
        public BudgetPlan $budget,
        public array $targeting,
        public array $assetIds,
        public string $pageId,
        public ?string $instagramUserId,
        public string $message,
        public ?string $headline = null,
        public ?string $link = null,
        public ?string $callToAction = null,
        public ?string $conversionDomain = null,
        public array $specialAdCategories = [],
        public array $promotedObject = [],
        public bool $advantagePlusCreative = false,
        public ?int $userId = null,
        public ActionOrigin $origin = ActionOrigin::UI,
        public ?int $launchReference = null,
    ) {}
}
