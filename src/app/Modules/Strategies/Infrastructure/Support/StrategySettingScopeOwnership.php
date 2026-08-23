<?php

declare(strict_types=1);

namespace App\Modules\Strategies\Infrastructure\Support;

use App\Modules\Settings\Domain\Contracts\SettingScopeOwnershipInterface;
use App\Modules\Settings\Domain\Enums\SettingScope;
use App\Modules\Strategies\Domain\Contracts\StrategyRepositoryInterface;

/**
 * Strategies answering Settings' question. It reads the repository rather than
 * StrategyService because StrategyService resolves SettingsService for the budget cap —
 * going through the Service would close that into a cycle.
 *
 * Any scope other than `strategy` is denied rather than allowed: this adapter only speaks
 * for the scope it owns, and guessing on behalf of another module fails open.
 */
readonly class StrategySettingScopeOwnership implements SettingScopeOwnershipInterface
{
    public function __construct(private StrategyRepositoryInterface $repository) {}

    public function ownsScope(SettingScope $scope, int $scopeId, int $accountId): bool
    {
        return $scope === SettingScope::STRATEGY
            && $this->repository->findById($scopeId, $accountId) !== null;
    }
}
