<?php

declare(strict_types=1);

namespace App\Modules\Brands\Infrastructure\Support;

use App\Modules\Brands\Domain\Contracts\BrandProfileUsageProviderInterface;

/**
 * The default when no module has claimed brand profile usage. It fails closed on purpose:
 * an unanswered "is this in use?" must never be read as "safe to delete", because the
 * profile is the root of every strategy and experiment underneath it.
 */
readonly class BrandProfileAssumedInUse implements BrandProfileUsageProviderInterface
{
    public function isInUse(int $brandProfileId, int $accountId): bool
    {
        return true;
    }
}
