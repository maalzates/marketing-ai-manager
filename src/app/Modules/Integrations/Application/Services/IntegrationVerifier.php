<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Application\Services;

use App\Modules\Integrations\Domain\Contracts\VerificationClientFactoryInterface;
use App\Modules\Integrations\Domain\Enums\IntegrationKind;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use App\Modules\Integrations\Domain\Enums\VerificationFailure;
use App\Modules\Integrations\Domain\Exceptions\UnsupportedIntegrationProviderException;
use App\Modules\Integrations\Domain\Support\VerificationOutcome;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;
use Illuminate\Support\Str;

readonly class IntegrationVerifier
{
    private const string MALFORMED_KEY_REASON = 'key_prefix_mismatch';

    private const string MISSING_CREDENTIAL_REASON = 'credential_missing';

    public function __construct(
        private VerificationClientFactoryInterface $clients,
        private GoogleOAuthService $google,
        private MetaOAuthService $meta,
    ) {}

    public function verify(Integration $integration): VerificationOutcome
    {
        return $integration->kind === IntegrationKind::API_KEY
            ? $this->verifyApiKey($integration->provider, (string) ($integration->credentials['api_key'] ?? ''))
            : $this->verifyOAuth($integration->provider, (string) ($integration->credentials['access_token'] ?? ''));
    }

    private function verifyApiKey(IntegrationProvider $provider, string $apiKey): VerificationOutcome
    {
        // A key that cannot possibly be valid should not cost a round trip to the provider.
        return self::hasExpectedPrefix($provider, $apiKey)
            ? $this->callProvider($provider, $apiKey)
            : VerificationOutcome::failed(VerificationFailure::CREDENTIAL_REJECTED, null, ['reason' => self::MALFORMED_KEY_REASON]);
    }

    private function verifyOAuth(IntegrationProvider $provider, string $accessToken): VerificationOutcome
    {
        return match (true) {
            $accessToken === '' => VerificationOutcome::failed(VerificationFailure::CREDENTIAL_REJECTED, null, ['reason' => self::MISSING_CREDENTIAL_REASON]),
            $provider->usesGoogleOAuth() => $this->google->verify($accessToken),
            $provider === IntegrationProvider::META => $this->meta->verify($accessToken),
            default => throw UnsupportedIntegrationProviderException::notImplemented($provider),
        };
    }

    private function callProvider(IntegrationProvider $provider, string $apiKey): VerificationOutcome
    {
        return match ($provider) {
            IntegrationProvider::ANTHROPIC => $this->clients->anthropic($apiKey)->verify(),
            IntegrationProvider::OPENAI => $this->clients->openAi($apiKey)->verify(),
            IntegrationProvider::GEMINI => $this->clients->gemini($apiKey)->verify(),
            IntegrationProvider::APIFY => $this->clients->apify($apiKey)->verify(),
            default => throw UnsupportedIntegrationProviderException::notImplemented($provider),
        };
    }

    private static function hasExpectedPrefix(IntegrationProvider $provider, string $apiKey): bool
    {
        $prefixes = config("services.{$provider->value}.key_prefixes", []);

        // Apify publishes no stable prefix, so there is nothing cheap to check first.
        return $apiKey !== '' && ($prefixes === [] || Str::startsWith($apiKey, $prefixes));
    }
}
