<?php

declare(strict_types=1);

namespace App\Modules\Ai;

use App\Modules\Ai\Domain\Contracts\AnalysisCacheRepositoryInterface;
use App\Modules\Ai\Domain\Contracts\LlmClientFactoryInterface;
use App\Modules\Ai\Infrastructure\Clients\LlmClientFactory;
use App\Modules\Ai\Infrastructure\Repositories\AnalysisCacheRepository;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The factory is shared; the clients it builds never are. Each carries one account's
        // key and is discarded with the call that asked for it.
        $this->app->singleton(LlmClientFactoryInterface::class, LlmClientFactory::class);
        $this->app->bind(AnalysisCacheRepositoryInterface::class, AnalysisCacheRepository::class);
    }
}
