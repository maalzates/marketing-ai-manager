<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Application\DTO;

use App\Modules\Competitors\Domain\Enums\CompetitorPlatform;

readonly class CompetitorFilterDTO
{
    public function __construct(
        public int $accountId,
        public ?CompetitorPlatform $platform = null,
        public ?int $strategyId = null,
        public ?bool $isActive = null,
        public int $perPage = 0,
        public int $page = 1,
    ) {}
}
