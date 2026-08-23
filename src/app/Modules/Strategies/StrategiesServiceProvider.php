<?php

declare(strict_types=1);

namespace App\Modules\Strategies;

use App\Modules\Brands\Domain\Contracts\BrandProfileUsageProviderInterface;
use App\Modules\Settings\Domain\Contracts\SettingScopeOwnershipInterface;
use App\Modules\Strategies\Domain\Contracts\StrategyRepositoryInterface;
use App\Modules\Strategies\Domain\Contracts\StrategyWorkloadProviderInterface;
use App\Modules\Strategies\Infrastructure\Repositories\StrategyRepository;
use App\Modules\Strategies\Infrastructure\Support\EmptyStrategyWorkload;
use App\Modules\Strategies\Infrastructure\Support\StrategyBrandProfileUsage;
use App\Modules\Strategies\Infrastructure\Support\StrategySettingScopeOwnership;
use Illuminate\Support\ServiceProvider;

class StrategiesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StrategyRepositoryInterface::class, StrategyRepository::class);

        // Strategies answers Brands' question, so Strategies registers the implementation.
        $this->app->bind(BrandProfileUsageProviderInterface::class, StrategyBrandProfileUsage::class);

        // Likewise for Settings: plain bind, overriding the deny-by-default that
        // SettingsServiceProvider registers with bindIf.
        $this->app->bind(SettingScopeOwnershipInterface::class, StrategySettingScopeOwnership::class);

        // bindIf, not bind: the module that owns the work under a strategy registers the real
        // implementation, and provider order must not decide which of the two wins.
        $this->app->bindIf(StrategyWorkloadProviderInterface::class, EmptyStrategyWorkload::class);
    }
}
