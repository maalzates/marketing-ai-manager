<?php

declare(strict_types=1);

namespace App\Modules\Brands;

use App\Modules\Brands\Domain\Contracts\BrandProfileRepositoryInterface;
use App\Modules\Brands\Domain\Contracts\BrandProfileUsageProviderInterface;
use App\Modules\Brands\Infrastructure\Repositories\BrandProfileRepository;
use App\Modules\Brands\Infrastructure\Support\BrandProfileAssumedInUse;
use Illuminate\Support\ServiceProvider;

class BrandsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BrandProfileRepositoryInterface::class, BrandProfileRepository::class);

        // bindIf, so the module that actually pins brand profiles wins whichever provider
        // registers first. The fallback refuses the delete rather than guessing.
        $this->app->bindIf(BrandProfileUsageProviderInterface::class, BrandProfileAssumedInUse::class);
    }
}
