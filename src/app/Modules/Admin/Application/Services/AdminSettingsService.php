<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Services;

use App\Modules\Admin\Application\DTO\WriteGlobalSettingsDTO;
use App\Modules\Admin\Domain\Exceptions\GlobalScopeForbiddenForSettingException;
use App\Modules\Audit\Application\DTO\RecordActionDTO;
use App\Modules\Audit\Application\Services\ActionLogService;
use App\Modules\Audit\Domain\Enums\ActionOrigin;
use App\Modules\Settings\Application\Services\SettingsService;
use App\Modules\Settings\Domain\Enums\SettingScope;
use Illuminate\Support\Collection;

/**
 * The admin panel writes through the existing registry — rate limits, defaults for new
 * accounts, feature flags, retention, maintenance and the job kill-switch are all keys of
 * config/settings.php, not tables of their own. A second store would be the hardcoding
 * core.md §12 forbids, moved somewhere less visible.
 */
readonly class AdminSettingsService
{
    private const string UPDATED_ACTION = 'admin.settings.updated';

    /**
     * Keys naming one external account. A global value is the fallback for every tenant that
     * has not set its own, so writing one of these globally would point every account's
     * campaigns — and their spend — at a single Meta ad account. The registry cannot yet mark
     * a key account-only, so the admin screen is what stands in front of it.
     *
     * @var list<string>
     */
    private const array ACCOUNT_ONLY_KEYS = [
        'campaigns.meta_ad_account_id',
        'campaigns.meta_sandbox_ad_account_id',
    ];

    public function __construct(
        private SettingsService $settings,
        private ActionLogService $actionLog,
    ) {}

    /** @return Collection<string, array{value: mixed, scope: string}> */
    public function all(?int $accountId = null): Collection
    {
        return $accountId === null
            ? $this->settings->all()->except(self::ACCOUNT_ONLY_KEYS)
            : $this->settings->all($accountId);
    }

    /** @return Collection<string, array{value: mixed, scope: string}> */
    public function update(WriteGlobalSettingsDTO $dto): Collection
    {
        self::assertScopeAllows($dto);

        foreach ($dto->values as $key => $value) {
            $this->settings->set(
                $dto->accountId === null ? SettingScope::GLOBAL : SettingScope::ACCOUNT,
                $dto->accountId,
                (string) $key,
                $value,
            );
        }

        $this->actionLog->record(new RecordActionDTO(
            $dto->accountId,
            $dto->actorUserId,
            self::UPDATED_ACTION,
            ActionOrigin::UI,
            ['keys' => array_keys($dto->values), 'scope' => $dto->accountId === null ? 'global' : 'account'],
        ));

        return $this->all($dto->accountId);
    }

    private static function assertScopeAllows(WriteGlobalSettingsDTO $dto): void
    {
        $forbidden = $dto->accountId === null
            ? array_values(array_intersect(array_keys($dto->values), self::ACCOUNT_ONLY_KEYS))
            : [];

        if ($forbidden !== []) {
            throw GlobalScopeForbiddenForSettingException::forKeys($forbidden);
        }
    }
}
