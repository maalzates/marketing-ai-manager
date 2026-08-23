<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\DTO;

use App\Modules\Audit\Domain\Enums\ActionOrigin;

readonly class PauseCampaignDTO
{
    public function __construct(
        public int $accountId,
        public int $experimentId,
        public ?string $reason = null,
        public ?int $userId = null,
        public ActionOrigin $origin = ActionOrigin::UI,
    ) {}
}
