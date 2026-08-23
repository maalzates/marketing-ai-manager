<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Clients;

use App\Modules\Assets\Domain\Contracts\DriveClientFactoryInterface;
use App\Modules\Core\Infrastructure\Clients\GuzzleClientFactory;
use App\Modules\Integrations\Application\Services\CredentialResolver;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;

/**
 * One Drive client per account, built from that account's own Google token. The client is
 * never cached: a singleton would hand every account the first user's Drive.
 */
readonly class DriveClientFactory implements DriveClientFactoryInterface
{
    public function __construct(
        private GuzzleClientFactory $guzzle,
        private CredentialResolver $credentials,
    ) {}

    public function forAccount(int $accountId): DriveClient
    {
        return new DriveClient(
            $this->guzzle->create([
                'base_uri' => config('services.google.drive_base_url'),
                'headers' => [
                    'Authorization' => 'Bearer '.$this->credentials->accessToken($accountId, IntegrationProvider::GOOGLE),
                ],
                // A 300 MB reel takes far longer than the shared 30 s policy; the transfer is
                // bounded by the chunk loop and the connect timeout, not by a wall clock.
                'timeout' => 0,
            ]),
            config('services.google.drive_upload_url'),
        );
    }
}
