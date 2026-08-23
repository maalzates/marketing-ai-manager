<?php

declare(strict_types=1);

namespace App\Modules\Competitors;

use App\Modules\Competitors\Domain\Contracts\CompetitorCommentRepositoryInterface;
use App\Modules\Competitors\Domain\Contracts\CompetitorDataProviderInterface;
use App\Modules\Competitors\Domain\Contracts\CompetitorPostRepositoryInterface;
use App\Modules\Competitors\Domain\Contracts\CompetitorRepositoryInterface;
use App\Modules\Competitors\Domain\Contracts\InsightRepositoryInterface;
use App\Modules\Competitors\Domain\Contracts\RefutedHypothesisSourceInterface;
use App\Modules\Competitors\Infrastructure\Adapters\NoRefutedHypothesisSource;
use App\Modules\Competitors\Infrastructure\Clients\ApifyClientFactory;
use App\Modules\Competitors\Infrastructure\Providers\ApifyCompetitorProvider;
use App\Modules\Competitors\Infrastructure\Repositories\CompetitorCommentRepository;
use App\Modules\Competitors\Infrastructure\Repositories\CompetitorPostRepository;
use App\Modules\Competitors\Infrastructure\Repositories\CompetitorRepository;
use App\Modules\Competitors\Infrastructure\Repositories\InsightRepository;
use Illuminate\Support\ServiceProvider;

class CompetitorsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CompetitorRepositoryInterface::class, CompetitorRepository::class);
        $this->app->bind(CompetitorPostRepositoryInterface::class, CompetitorPostRepository::class);
        $this->app->bind(CompetitorCommentRepositoryInterface::class, CompetitorCommentRepository::class);
        $this->app->bind(InsightRepositoryInterface::class, InsightRepository::class);

        // Swapping Apify for another scraper is this line and one new class.
        $this->app->bind(CompetitorDataProviderInterface::class, ApifyCompetitorProvider::class);

        $this->app->bind(RefutedHypothesisSourceInterface::class, NoRefutedHypothesisSource::class);

        // The factory is shared; the clients it builds carry one account's token and are not.
        $this->app->singleton(ApifyClientFactory::class);
    }
}
