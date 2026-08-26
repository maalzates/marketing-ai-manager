<?php

declare(strict_types=1);

namespace App\Modules\Ai\Infrastructure\Clients;

use App\Modules\Ai\Domain\Contracts\ModelListClientFactoryInterface;
use App\Modules\Ai\Domain\Contracts\ModelListClientInterface;
use App\Modules\Ai\Domain\Enums\LlmProvider;
use App\Modules\Core\Infrastructure\Clients\GuzzleClientFactory;
use App\Modules\Integrations\Application\Services\CredentialResolver;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;

/** Same rule as `LlmClientFactory`: one client per call, around one account's own key. */
readonly class ModelListClientFactory implements ModelListClientFactoryInterface
{
    public function __construct(
        private GuzzleClientFactory $guzzle,
        private CredentialResolver $credentials,
    ) {}

    public function forAccount(int $accountId, LlmProvider $provider): ModelListClientInterface
    {
        return match ($provider) {
            LlmProvider::Anthropic => new AnthropicModelListClient($this->guzzle->create([
                'base_uri' => $provider->baseUrl(),
                'headers' => [
                    'x-api-key' => $this->apiKey($accountId, $provider),
                    'anthropic-version' => config('services.anthropic.version'),
                ],
            ])),
            LlmProvider::OpenAi => new OpenAiModelListClient($this->guzzle->create([
                'base_uri' => $provider->baseUrl(),
                'headers' => ['Authorization' => 'Bearer '.$this->apiKey($accountId, $provider)],
            ])),
            LlmProvider::Gemini => new GeminiModelListClient($this->guzzle->create([
                'base_uri' => $provider->baseUrl(),
                'headers' => ['x-goog-api-key' => $this->apiKey($accountId, $provider)],
            ])),
        };
    }

    private function apiKey(int $accountId, LlmProvider $provider): string
    {
        return $this->credentials->apiKey($accountId, IntegrationProvider::from($provider->value));
    }
}
