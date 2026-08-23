<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Services;

use App\Modules\Accounts\Application\DTO\AccountFilterDTO;
use App\Modules\Accounts\Application\Services\AccountService;
use App\Modules\Campaigns\Domain\ValueObjects\AdsAccountTarget;

/**
 * Sandbox is a property of the account, not of the call, so it is read here once per
 * operation instead of being threaded through every door as a parameter somebody can forget.
 */
readonly class AdsTargetResolver
{
    public function __construct(private AccountService $accounts) {}

    public function forAccount(int $accountId): AdsAccountTarget
    {
        return new AdsAccountTarget(
            $accountId,
            $this->accounts->findActiveById(new AccountFilterDTO($accountId))->sandbox_mode,
        );
    }
}
