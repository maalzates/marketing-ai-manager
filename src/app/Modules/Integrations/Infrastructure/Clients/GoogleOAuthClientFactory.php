<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Infrastructure\Clients;

use App\Modules\Core\Infrastructure\Clients\GuzzleClientFactory;
use App\Modules\Integrations\Domain\Contracts\GoogleOAuthClientFactoryInterface;
use GuzzleHttp\Client;

readonly class GoogleOAuthClientFactory implements GoogleOAuthClientFactoryInterface
{
    public function __construct(private GuzzleClientFactory $guzzle) {}

    public function create(): GoogleOAuthClient
    {
        return $this->build($this->guzzle->create([
            // Google's token and revoke endpoints only accept form encoding.
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
        ]));
    }

    public function forAccessToken(string $accessToken): GoogleOAuthClient
    {
        return $this->build($this->guzzle->create([
            'headers' => ['Authorization' => "Bearer {$accessToken}"],
        ]));
    }

    private function build(Client $client): GoogleOAuthClient
    {
        return new GoogleOAuthClient(
            $client,
            config('services.google.token_url'),
            config('services.google.revoke_url'),
            config('services.google.userinfo_url'),
            (string) config('services.google.client_id'),
            (string) config('services.google.client_secret'),
        );
    }
}
