<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application\DTO;

use App\Modules\Audit\Domain\Enums\UsageGrouping;
use Carbon\CarbonImmutable;

/**
 * A null accountId reads every account's consumption, so it is not reachable by passing one:
 * the two named constructors force the caller to say which of the two it wants.
 */
readonly class UsageFilterDTO
{
    private function __construct(
        public ?int $accountId,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public UsageGrouping $groupBy,
    ) {}

    public static function forAccount(
        int $accountId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        UsageGrouping $groupBy,
    ): self {
        return new self($accountId, $from, $to, $groupBy);
    }

    public static function acrossAllAccounts(
        CarbonImmutable $from,
        CarbonImmutable $to,
        UsageGrouping $groupBy,
    ): self {
        return new self(null, $from, $to, $groupBy);
    }
}
