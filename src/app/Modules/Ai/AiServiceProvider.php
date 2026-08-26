<?php

declare(strict_types=1);

namespace App\Modules\Ai;

use App\Modules\Ai\Application\Services\LlmModelCatalog;
use App\Modules\Ai\Domain\Contracts\AnalysisCacheRepositoryInterface;
use App\Modules\Ai\Domain\Contracts\LlmClientFactoryInterface;
use App\Modules\Ai\Domain\Contracts\ModelCatalogRepositoryInterface;
use App\Modules\Ai\Domain\Contracts\ModelListClientFactoryInterface;
use App\Modules\Ai\Infrastructure\Clients\LlmClientFactory;
use App\Modules\Ai\Infrastructure\Clients\ModelListClientFactory;
use App\Modules\Ai\Infrastructure\Repositories\AnalysisCacheRepository;
use App\Modules\Ai\Infrastructure\Repositories\ModelCatalogRepository;
use App\Modules\Ai\Presentation\Console\RefreshModelCatalogCommand;
use App\Modules\Integrations\Domain\Contracts\LlmModelCatalogInterface;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The factory is shared; the clients it builds never are. Each carries one account's
        // key and is discarded with the call that asked for it.
        $this->app->singleton(LlmClientFactoryInterface::class, LlmClientFactory::class);
        $this->app->bind(AnalysisCacheRepositoryInterface::class, AnalysisCacheRepository::class);

        // `Integrations` owns the question and `Ai` answers it, which is what keeps the two
        // modules from depending on each other in both directions.
        $this->app->bind(LlmModelCatalogInterface::class, LlmModelCatalog::class);

        // The list client is built per call, like the completion client: it carries one
        // account's key and must never be shared between requests.
        $this->app->bind(ModelListClientFactoryInterface::class, ModelListClientFactory::class);
        $this->app->bind(ModelCatalogRepositoryInterface::class, ModelCatalogRepository::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([RefreshModelCatalogCommand::class]);
        }
    }
}
