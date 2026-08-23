<?php

declare(strict_types=1);

namespace App\Modules\Core\Application\Context;

/**
 * The account the current request, job or chat turn acts on. Bound as a scoped instance by
 * EnsureAccountContext, so resolving it anywhere else fails loudly instead of silently
 * serving another tenant's account.
 */
readonly class AccountContext
{
    public function __construct(public int $accountId, public int $userId) {}
}
