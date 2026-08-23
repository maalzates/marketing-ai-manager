<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Infrastructure\Clients;

use App\Modules\Core\Domain\Support\SecretMasker;
use App\Modules\Core\Infrastructure\Clients\GuzzleClientFactory;
use App\Modules\Integrations\Application\Services\CredentialResolver;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;

/**
 * The factory is the singleton, never the client: an Apify client carries one account's
 * token, and a cached one would scrape every other account on that token.
 */
readonly class ApifyClientFactory
{
    private const int TIMEOUT_MARGIN_SECONDS = 15;

    public function __construct(
        private GuzzleClientFactory $guzzle,
        private CredentialResolver $credentials,
        private SecretMasker $masker,
    ) {}

    public function forAccount(int $accountId): ApifyClient
    {
        return new ApifyClient(
            $this->guzzle->create([
                'base_uri' => config('services.apify.base_url'),
                'headers' => [
                    'Authorization' => 'Bearer '.$this->credentials->apiKey($accountId, IntegrationProvider::APIFY),
                ],
                'timeout' => ApifyClient::WAIT_FOR_FINISH_SECONDS + self::TIMEOUT_MARGIN_SECONDS,
            ]),
            $this->masker,
        );
    }
}
