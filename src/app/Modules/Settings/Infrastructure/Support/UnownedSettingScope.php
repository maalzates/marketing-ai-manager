<?php

declare(strict_types=1);

namespace App\Modules\Settings\Infrastructure\Support;

use App\Modules\Settings\Domain\Contracts\SettingScopeOwnershipInterface;
use App\Modules\Settings\Domain\Enums\SettingScope;

/**
 * The default when no module has claimed a scope. It fails closed on purpose: an unanswered
 * "does this account own that scope?" must never be read as yes, or an unclaimed scope
 * becomes a cross-tenant write on the day it is introduced.
 */
readonly class UnownedSettingScope implements SettingScopeOwnershipInterface
{
    public function ownsScope(SettingScope $scope, int $scopeId, int $accountId): bool
    {
        return false;
    }
}
