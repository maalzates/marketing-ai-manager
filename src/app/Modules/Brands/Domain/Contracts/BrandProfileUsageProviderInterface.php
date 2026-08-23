<?php

declare(strict_types=1);

namespace App\Modules\Brands\Domain\Contracts;

/**
 * Whether anything downstream is still pinned to a brand profile. Brands asks the question
 * and owns the contract; it deliberately says nothing about what the user of a profile is,
 * so a second kind of consumer needs no new interface.
 */
interface BrandProfileUsageProviderInterface
{
    public function isInUse(int $brandProfileId, int $accountId): bool;
}
