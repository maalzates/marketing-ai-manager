<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Domain\ValueObjects;

use App\Modules\Campaigns\Domain\Enums\CampaignObjective;

readonly class CampaignSpec
{
    /**
     * @param  list<string>  $specialAdCategories
     */
    public function __construct(
        public string $name,
        public CampaignObjective $objective,
        public array $specialAdCategories = [],
    ) {}
}
