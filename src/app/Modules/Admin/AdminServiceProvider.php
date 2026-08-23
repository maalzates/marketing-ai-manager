<?php

declare(strict_types=1);

namespace App\Modules\Admin;

use App\Modules\Accounts\Application\Services\AccountService;
use App\Modules\Admin\Domain\Contracts\AdminUserRepositoryInterface;
use App\Modules\Admin\Domain\Contracts\ApplicationApiKeyRepositoryInterface;
use App\Modules\Admin\Infrastructure\Repositories\AdminUserRepository;
use App\Modules\Admin\Infrastructure\Repositories\ApplicationApiKeyRepository;
use App\Modules\Settings\Application\Services\SettingsService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    private const string DEFAULT_LIMIT_SETTING = 'rate_limits.default_per_minute';

    private const string CHAT_LIMIT_SETTING = 'rate_limits.chat_per_minute';

    private const string ADMIN_LIMIT_SETTING = 'rate_limits.admin_per_minute';

    public function register(): void
    {
        $this->app->bind(ApplicationApiKeyRepositoryInterface::class, ApplicationApiKeyRepository::class);
        $this->app->bind(AdminUserRepositoryInterface::class, AdminUserRepository::class);
    }

    public function boot(): void
    {
        $this->limiter('api', self::DEFAULT_LIMIT_SETTING);
        $this->limiter('chat', self::CHAT_LIMIT_SETTING);
        $this->limiter('admin', self::ADMIN_LIMIT_SETTING);
    }

    /**
     * The limit is read per request rather than captured at boot, so raising it from the
     * admin panel takes effect on the next call instead of the next deploy (core.md §12).
     */
    private function limiter(string $name, string $settingKey): void
    {
        RateLimiter::for($name, fn (Request $request): Limit => Limit::perMinute(
            (int) $this->app->make(SettingsService::class)->get($settingKey, $this->accountIdOf($request)),
        )->by((string) ($request->user()?->id ?? $request->ip())));
    }

    /** The account scope is what a per-user override is stored against; anonymous traffic gets the global default. */
    private function accountIdOf(Request $request): ?int
    {
        return $request->user() === null
            ? null
            : $this->app->make(AccountService::class)->findAllForUser((int) $request->user()->id)->first()?->id;
    }
}
