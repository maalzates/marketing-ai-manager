<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\DTO;

/**
 * Meta's own documentation gives two different publishing quotas, so the total is read
 * from the API on every run and never assumed.
 */
readonly class PublishingLimitDTO
{
    public function __construct(
        public int $quotaUsage,
        public int $quotaTotal,
        public int $quotaDurationSeconds,
    ) {}

    public function hasHeadroom(): bool
    {
        return $this->quotaUsage < $this->quotaTotal;
    }
}
