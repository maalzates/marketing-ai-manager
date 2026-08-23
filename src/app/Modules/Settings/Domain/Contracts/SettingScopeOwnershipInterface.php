<?php

declare(strict_types=1);

namespace App\Modules\Settings\Domain\Contracts;

use App\Modules\Settings\Domain\Enums\SettingScope;

/**
 * Whether an account may write settings onto a scope. Settings asks the question and owns
 * the contract; it says nothing about what a scope *is*, so the next scope this registry
 * grows needs no new interface — only a module willing to answer for it.
 */
interface SettingScopeOwnershipInterface
{
    public function ownsScope(SettingScope $scope, int $scopeId, int $accountId): bool;
}
