<?php

declare(strict_types=1);

namespace App\Modules\Campaigns;

use App\Modules\Assets\Domain\Contracts\MetaMediaLibraryInterface;
use App\Modules\Campaigns\Domain\Contracts\AdsProviderInterface;
use App\Modules\Campaigns\Domain\Contracts\CampaignLaunchObserverInterface;
use App\Modules\Campaigns\Domain\Contracts\CampaignRepositoryInterface;
use App\Modules\Campaigns\Infrastructure\Clients\MetaAdsClientFactory;
use App\Modules\Campaigns\Infrastructure\Observers\NullCampaignLaunchObserver;
use App\Modules\Campaigns\Infrastructure\Providers\MetaAdsProvider;
use App\Modules\Campaigns\Infrastructure\Providers\MetaMediaLibrary;
use App\Modules\Campaigns\Infrastructure\Repositories\CampaignRepository;
use Illuminate\Support\ServiceProvider;

class CampaignsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CampaignRepositoryInterface::class, CampaignRepository::class);

        // Meta is the only ads platform today; the seam is what lets a second one arrive
        // without the domain learning its name.
        $this->app->bind(AdsProviderInterface::class, MetaAdsProvider::class);

        // Assets defines the port and deliberately leaves it unbound: the Meta client is ours,
        // and binding it there would point Assets at the module that depends on it.
        $this->app->bind(MetaMediaLibraryInterface::class, MetaMediaLibrary::class);

        // bindIf, not bind: the module that authorises launches binds the real observer, and
        // this default must lose to it regardless of which provider the app loads first.
        $this->app->bindIf(CampaignLaunchObserverInterface::class, NullCampaignLaunchObserver::class);

        // The factory is shared, the clients it builds are not: a singleton client would
        // carry the first account's token and ad account into every later request.
        $this->app->singleton(MetaAdsClientFactory::class);
    }
}
