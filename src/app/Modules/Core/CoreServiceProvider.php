<?php

declare(strict_types=1);

namespace App\Modules\Core;

use App\Modules\Core\Infrastructure\Clients\GuzzleClientFactory;
use App\Modules\Core\Presentation\Http\Responses\ApiResponse;
use Illuminate\Support\ServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GuzzleClientFactory::class);
        $this->app->singleton(ApiResponse::class);
    }
}
