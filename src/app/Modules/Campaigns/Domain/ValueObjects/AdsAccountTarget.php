<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Domain\ValueObjects;

/**
 * Which tenant a provider call acts for, and whether it acts against that tenant's
 * sandbox. The credential itself never travels in a value object — the provider resolves
 * it per call and discards it.
 */
readonly class AdsAccountTarget
{
    public function __construct(public int $accountId, public bool $sandbox) {}
}
