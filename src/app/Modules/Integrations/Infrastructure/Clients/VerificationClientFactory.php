<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Infrastructure\Clients;

use App\Modules\Core\Infrastructure\Clients\GuzzleClientFactory;
use App\Modules\Integrations\Domain\Contracts\VerificationClientFactoryInterface;

readonly class VerificationClientFactory implements VerificationClientFactoryInterface
{
    public function __construct(private GuzzleClientFactory $guzzle) {}

    public function anthropic(string $apiKey): AnthropicVerificationClient
    {
        return new AnthropicVerificationClient($this->guzzle->create([
            'base_uri' => config('services.anthropic.base_url'),
            'headers' => [
                'x-api-key' => $apiKey,
                'anthropic-version' => config('services.anthropic.version'),
            ],
        ]));
    }

    public function openAi(string $apiKey): OpenAiVerificationClient
    {
        return new OpenAiVerificationClient($this->guzzle->create([
            'base_uri' => config('services.openai.base_url'),
            'headers' => ['Authorization' => "Bearer {$apiKey}"],
        ]));
    }

    public function gemini(string $apiKey): GeminiVerificationClient
    {
        return new GeminiVerificationClient($this->guzzle->create([
            'base_uri' => config('services.gemini.base_url'),
            'headers' => ['x-goog-api-key' => $apiKey],
        ]));
    }

    public function apify(string $apiKey): ApifyVerificationClient
    {
        return new ApifyVerificationClient($this->guzzle->create([
            'base_uri' => config('services.apify.base_url'),
            'headers' => ['Authorization' => "Bearer {$apiKey}"],
        ]));
    }
}
