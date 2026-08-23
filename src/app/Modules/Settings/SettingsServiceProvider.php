<?php

declare(strict_types=1);

namespace App\Modules\Settings;

use App\Modules\Settings\Domain\Contracts\SettingRepositoryInterface;
use App\Modules\Settings\Domain\Contracts\SettingScopeOwnershipInterface;
use App\Modules\Settings\Infrastructure\Repositories\SettingRepository;
use App\Modules\Settings\Infrastructure\Support\UnownedSettingScope;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SettingRepositoryInterface::class, SettingRepository::class);

        // bindIf, and the default denies: the module that owns a scope registers the real
        // answer, and until one does no account can write onto that scope at all.
        $this->app->bindIf(SettingScopeOwnershipInterface::class, UnownedSettingScope::class);
    }
}
