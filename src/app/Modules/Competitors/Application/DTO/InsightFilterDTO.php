<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Application\DTO;

use App\Modules\Competitors\Domain\Enums\InsightKind;
use App\Modules\Competitors\Domain\Enums\InsightStatus;

readonly class InsightFilterDTO
{
    public function __construct(
        public int $accountId,
        public ?InsightKind $kind = null,
        public ?InsightStatus $status = null,
        public ?int $strategyId = null,
        public ?int $competitorId = null,
        public int $perPage = 0,
        public int $page = 1,
    ) {}
}
