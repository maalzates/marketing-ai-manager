<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Infrastructure\Clients;

use App\Modules\Core\Infrastructure\Clients\GuzzleClientFactory;
use App\Modules\Integrations\Domain\Contracts\MetaOAuthClientFactoryInterface;

readonly class MetaOAuthClientFactory implements MetaOAuthClientFactoryInterface
{
    public function __construct(private GuzzleClientFactory $guzzle) {}

    public function create(): MetaOAuthClient
    {
        return new MetaOAuthClient(
            $this->guzzle->create(['base_uri' => config('services.meta.graph_base_url')]),
            (string) config('services.meta.graph_version'),
            (string) config('services.meta.app_id'),
            (string) config('services.meta.app_secret'),
        );
    }
}
