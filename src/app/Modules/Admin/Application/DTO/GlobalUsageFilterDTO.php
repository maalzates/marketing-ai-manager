<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\DTO;

use App\Modules\Audit\Domain\Enums\UsageGrouping;
use Carbon\CarbonImmutable;

readonly class GlobalUsageFilterDTO
{
    public function __construct(
        public ?int $accountId,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public UsageGrouping $groupBy,
    ) {}
}
