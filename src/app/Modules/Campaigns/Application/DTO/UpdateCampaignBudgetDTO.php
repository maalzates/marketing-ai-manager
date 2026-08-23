<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\DTO;

use App\Modules\Audit\Domain\Enums\ActionOrigin;
use App\Modules\Campaigns\Domain\ValueObjects\BudgetPlan;

readonly class UpdateCampaignBudgetDTO
{
    public function __construct(
        public int $accountId,
        public int $experimentId,
        public BudgetPlan $budget,
        public ?int $userId = null,
        public ActionOrigin $origin = ActionOrigin::UI,
    ) {}
}
