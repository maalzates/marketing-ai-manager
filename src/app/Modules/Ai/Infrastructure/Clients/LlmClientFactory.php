<?php

declare(strict_types=1);

namespace App\Modules\Ai\Infrastructure\Clients;

use App\Modules\Ai\Domain\Contracts\LlmClientFactoryInterface;
use App\Modules\Ai\Domain\Contracts\LlmClientInterface;
use App\Modules\Ai\Domain\Enums\LlmProvider;
use App\Modules\Core\Infrastructure\Clients\GuzzleClientFactory;
use App\Modules\Integrations\Application\Services\CredentialResolver;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;

/**
 * Builds a client around the account's own key, per call. Nothing here is cached: a
 * singleton client — or a key held in shared container state — would serve one tenant's
 * credential to the next request that happens to land on the same worker.
 */
readonly class LlmClientFactory implements LlmClientFactoryInterface
{
    public function __construct(
        private GuzzleClientFactory $guzzle,
        private CredentialResolver $credentials,
    ) {}

    public function forAccount(int $accountId, LlmProvider $provider): LlmClientInterface
    {
        return match ($provider) {
            LlmProvider::Anthropic => new AnthropicClient($this->guzzle->create([
                'base_uri' => $provider->baseUrl(),
                'headers' => [
                    'x-api-key' => $this->apiKey($accountId, $provider),
                    'anthropic-version' => config('services.anthropic.version'),
                ],
            ])),
            LlmProvider::OpenAi => new OpenAiClient($this->guzzle->create([
                'base_uri' => $provider->baseUrl(),
                'headers' => ['Authorization' => 'Bearer '.$this->apiKey($accountId, $provider)],
            ])),
            LlmProvider::Gemini => new GeminiClient($this->guzzle->create([
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
