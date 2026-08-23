<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Domain\ValueObjects;

/**
 * Amounts in the ad account's own currency, as decimals. Converting them to the minor
 * units the platform expects happens once, inside the client.
 */
readonly class BudgetPlan
{
    public function __construct(public ?float $daily = null, public ?float $lifetime = null) {}

    public function total(int $durationDays): ?float
    {
        return match (true) {
            $this->lifetime !== null => $this->lifetime,
            $this->daily !== null => $this->daily * max(1, $durationDays),
            default => null,
        };
    }
}
