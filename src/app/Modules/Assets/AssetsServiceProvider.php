<?php

declare(strict_types=1);

namespace App\Modules\Assets;

use App\Modules\Assets\Domain\Contracts\AssetRepositoryInterface;
use App\Modules\Assets\Domain\Contracts\DriveClientFactoryInterface;
use App\Modules\Assets\Infrastructure\Clients\DriveClientFactory;
use App\Modules\Assets\Infrastructure\Repositories\AssetRepository;
use Illuminate\Support\ServiceProvider;

class AssetsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AssetRepositoryInterface::class, AssetRepository::class);

        // The factory is shared; the client it builds never is, because it carries one
        // account's Google token.
        $this->app->singleton(DriveClientFactoryInterface::class, DriveClientFactory::class);
    }
}
