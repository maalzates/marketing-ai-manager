<?php

declare(strict_types=1);

namespace App\Modules\Core\Infrastructure\Clients;

use GuzzleHttp\Client;

/**
 * Single place where outbound HTTP clients are built, so every external integration
 * inherits the same timeouts and headers instead of inventing its own.
 */
readonly class GuzzleClientFactory
{
    private const array DEFAULT_CONFIG = [
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ],
        'timeout' => 30,
        'connect_timeout' => 5,
        'http_errors' => true,
    ];

    public function create(array $config = []): Client
    {
        return new Client(array_replace_recursive(self::DEFAULT_CONFIG, $config));
    }
}
