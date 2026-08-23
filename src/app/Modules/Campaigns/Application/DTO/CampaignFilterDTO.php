<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\DTO;

use App\Modules\Campaigns\Domain\Enums\CampaignStatus;

readonly class CampaignFilterDTO
{
    public function __construct(
        public int $accountId,
        public ?int $experimentId = null,
        public ?CampaignStatus $status = null,
    ) {}
}
