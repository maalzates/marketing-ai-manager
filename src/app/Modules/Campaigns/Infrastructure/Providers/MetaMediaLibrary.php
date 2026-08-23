<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Infrastructure\Providers;

use App\Modules\Assets\Domain\Contracts\MetaMediaLibraryInterface;
use App\Modules\Campaigns\Application\Services\AdsTargetResolver;
use App\Modules\Campaigns\Infrastructure\Clients\MetaAdsClient;
use App\Modules\Campaigns\Infrastructure\Clients\MetaAdsClientFactory;

/**
 * Assets owns the port and Campaigns owns the Meta client, so the adapter lives here — and
 * the ad account, which Assets has no business knowing, is resolved on this side from the
 * account's sandbox mode like every other call in the module.
 */
readonly class MetaMediaLibrary implements MetaMediaLibraryInterface
{
    public function __construct(
        private MetaAdsClientFactory $clients,
        private AdsTargetResolver $targets,
    ) {}

    public function uploadImage(int $accountId, string $filename, string $fetchUrl): string
    {
        return $this->client($accountId)->uploadImage($filename, $fetchUrl);
    }

    public function uploadVideo(int $accountId, string $name, string $fileUrl): string
    {
        return $this->client($accountId)->uploadVideo($name, $fileUrl);
    }

    private function client(int $accountId): MetaAdsClient
    {
        return $this->clients->forAccount($this->targets->forAccount($accountId));
    }
}
