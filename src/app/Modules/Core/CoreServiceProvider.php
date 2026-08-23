<?php

declare(strict_types=1);

namespace App\Modules\Core;

use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Domain\Exceptions\AccountContextException;
use App\Modules\Core\Domain\Support\SecretMasker;
use App\Modules\Core\Infrastructure\Clients\GuzzleClientFactory;
use App\Modules\Core\Presentation\Http\Responses\ApiResponse;
use App\Modules\Core\Presentation\Tools\ToolRegistry;
use Illuminate\Support\ServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GuzzleClientFactory::class);
        $this->app->singleton(ApiResponse::class);
        $this->app->singleton(SecretMasker::class);
        $this->app->singleton(ToolRegistry::class);

        // Scoped, not singleton: EnsureAccountContext replaces this instance per request. The
        // default throws so anything reaching for an account outside that gate fails loudly.
        $this->app->scoped(
            AccountContext::class,
            static fn (): AccountContext => throw AccountContextException::notResolved(),
        );
    }
}
