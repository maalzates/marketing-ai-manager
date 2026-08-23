<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Clients;

use App\Modules\Content\Domain\Contracts\InstagramClientFactoryInterface;
use App\Modules\Core\Infrastructure\Clients\GuzzleClientFactory;
use App\Modules\Integrations\Application\Services\CredentialResolver;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;

/**
 * The factory is shared; the client it builds is not. A client held as a singleton would
 * carry the first account's Meta token into every later job on the same worker.
 */
readonly class InstagramClientFactory implements InstagramClientFactoryInterface
{
    public function __construct(
        private GuzzleClientFactory $guzzle,
        private CredentialResolver $credentials,
    ) {}

    public function forAccount(int $accountId): InstagramClient
    {
        return new InstagramClient(
            $this->guzzle->create([
                'base_uri' => config('services.meta.graph_base_url'),
                'headers' => [
                    'Authorization' => 'Bearer '.$this->credentials->accessToken($accountId, IntegrationProvider::META),
                ],
                // A reel container can take minutes to ingest; the poll itself is cheap,
                // but Meta answers slowly while it is chewing on a large upload.
                'timeout' => 60,
            ]),
            (string) config('services.meta.graph_version'),
        );
    }
}
