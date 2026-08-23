<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Domain\ValueObjects;

use App\Modules\Campaigns\Domain\Enums\CampaignObjective;
use Carbon\CarbonImmutable;

readonly class AdSetSpec
{
    /**
     * @param  array<string, mixed>  $targeting
     * @param  array<string, mixed>  $promotedObject
     */
    public function __construct(
        public string $name,
        public string $externalCampaignId,
        public CampaignObjective $objective,
        public BudgetPlan $budget,
        public array $targeting,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public array $promotedObject = [],
    ) {}
}
