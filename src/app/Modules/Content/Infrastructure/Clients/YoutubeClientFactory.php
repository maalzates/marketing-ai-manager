<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Clients;

use App\Modules\Content\Domain\Contracts\YoutubeClientFactoryInterface;
use App\Modules\Core\Infrastructure\Clients\GuzzleClientFactory;
use App\Modules\Integrations\Application\Services\CredentialResolver;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;

readonly class YoutubeClientFactory implements YoutubeClientFactoryInterface
{
    public function __construct(
        private GuzzleClientFactory $guzzle,
        private CredentialResolver $credentials,
    ) {}

    public function forAccount(int $accountId): YoutubeContentClient
    {
        return new YoutubeContentClient($this->guzzle->create([
            'base_uri' => config('services.google.youtube_base_url'),
            'headers' => [
                'Authorization' => 'Bearer '.$this->credentials->accessToken($accountId, IntegrationProvider::YOUTUBE),
            ],
        ]));
    }
}
