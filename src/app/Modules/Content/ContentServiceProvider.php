<?php

declare(strict_types=1);

namespace App\Modules\Content;

use App\Modules\Content\Domain\Contracts\AudienceSnapshotRepositoryInterface;
use App\Modules\Content\Domain\Contracts\ChannelProviderRegistryInterface;
use App\Modules\Content\Domain\Contracts\ContentScheduleRepositoryInterface;
use App\Modules\Content\Domain\Contracts\ContentScriptRepositoryInterface;
use App\Modules\Content\Domain\Contracts\InstagramClientFactoryInterface;
use App\Modules\Content\Domain\Contracts\YoutubeClientFactoryInterface;
use App\Modules\Content\Infrastructure\Clients\InstagramClientFactory;
use App\Modules\Content\Infrastructure\Clients\YoutubeClientFactory;
use App\Modules\Content\Infrastructure\Providers\ChannelProviderRegistry;
use App\Modules\Content\Infrastructure\Providers\InstagramChannelProvider;
use App\Modules\Content\Infrastructure\Providers\YoutubeChannelProvider;
use App\Modules\Content\Infrastructure\Repositories\AudienceSnapshotRepository;
use App\Modules\Content\Infrastructure\Repositories\ContentScheduleRepository;
use App\Modules\Content\Infrastructure\Repositories\ContentScriptRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class ContentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ContentScriptRepositoryInterface::class, ContentScriptRepository::class);
        $this->app->bind(ContentScheduleRepositoryInterface::class, ContentScheduleRepository::class);
        $this->app->bind(AudienceSnapshotRepositoryInterface::class, AudienceSnapshotRepository::class);

        // The factories are shared; the clients they build are not. A client bound as a
        // singleton would carry the first account's token into every later job.
        $this->app->singleton(InstagramClientFactoryInterface::class, InstagramClientFactory::class);
        $this->app->singleton(YoutubeClientFactoryInterface::class, YoutubeClientFactory::class);

        $this->app->singleton(ChannelProviderRegistryInterface::class, fn (Application $app) => new ChannelProviderRegistry([
            $app->make(InstagramChannelProvider::class),
            $app->make(YoutubeChannelProvider::class),
        ]));
    }
}
