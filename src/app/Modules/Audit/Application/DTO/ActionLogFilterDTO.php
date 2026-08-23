<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application\DTO;

use App\Modules\Audit\Domain\Enums\ActionOrigin;
use Carbon\CarbonImmutable;

/**
 * A null accountId reads every account's actions, so it is not reachable by passing one:
 * the two named constructors force the caller to say which of the two it wants.
 */
readonly class ActionLogFilterDTO
{
    private function __construct(
        public ?int $accountId,
        public ?string $action,
        public ?ActionOrigin $origin,
        public ?CarbonImmutable $from,
        public ?CarbonImmutable $to,
        public int $perPage,
        public int $page,
    ) {}

    public static function forAccount(
        int $accountId,
        ?string $action,
        ?ActionOrigin $origin,
        ?CarbonImmutable $from,
        ?CarbonImmutable $to,
        int $perPage,
        int $page,
    ): self {
        return new self($accountId, $action, $origin, $from, $to, $perPage, $page);
    }

    public static function acrossAllAccounts(
        ?string $action,
        ?ActionOrigin $origin,
        ?CarbonImmutable $from,
        ?CarbonImmutable $to,
        int $perPage,
        int $page,
    ): self {
        return new self(null, $action, $origin, $from, $to, $perPage, $page);
    }
}
