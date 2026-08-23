<?php

declare(strict_types=1);

namespace App\Modules\Integrations;

use App\Modules\Integrations\Domain\Contracts\GoogleOAuthClientFactoryInterface;
use App\Modules\Integrations\Domain\Contracts\IntegrationRepositoryInterface;
use App\Modules\Integrations\Domain\Contracts\MetaOAuthClientFactoryInterface;
use App\Modules\Integrations\Domain\Contracts\VerificationClientFactoryInterface;
use App\Modules\Integrations\Infrastructure\Clients\GoogleOAuthClientFactory;
use App\Modules\Integrations\Infrastructure\Clients\MetaOAuthClientFactory;
use App\Modules\Integrations\Infrastructure\Clients\VerificationClientFactory;
use App\Modules\Integrations\Infrastructure\Repositories\IntegrationRepository;
use Illuminate\Support\ServiceProvider;

class IntegrationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(IntegrationRepositoryInterface::class, IntegrationRepository::class);

        // The factories are shared; the clients they build are not. A client bound as a
        // singleton would carry the first account's credential into every later request.
        $this->app->singleton(VerificationClientFactoryInterface::class, VerificationClientFactory::class);
        $this->app->singleton(GoogleOAuthClientFactoryInterface::class, GoogleOAuthClientFactory::class);
        $this->app->singleton(MetaOAuthClientFactoryInterface::class, MetaOAuthClientFactory::class);
    }
}
