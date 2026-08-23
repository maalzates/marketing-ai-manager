<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Application\Services;

use App\Modules\Integrations\Domain\Contracts\IntegrationRepositoryInterface;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use App\Modules\Integrations\Domain\Exceptions\IntegrationNotConnectedException;
use App\Modules\Integrations\Domain\Exceptions\IntegrationTokenExpiredException;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;

/**
 * The only way any other module reads a live credential, which is why nothing else ever
 * touches the integrations table or decrypts a stored token.
 */
readonly class CredentialResolver
{
    private const int REFRESH_SKEW_MINUTES = 5;

    public function __construct(
        private IntegrationRepositoryInterface $repository,
        private GoogleOAuthService $google,
    ) {}

    public function apiKey(int $accountId, IntegrationProvider $provider): string
    {
        return $this->stored($accountId, $provider)->credentials['api_key']
            ?? throw IntegrationNotConnectedException::forProvider($provider, $accountId);
    }

    public function accessToken(int $accountId, IntegrationProvider $provider): string
    {
        $integration = $this->stored($accountId, $provider);

        return self::isUsable($integration)
            ? $integration->credentials['access_token']
            : $this->renew($integration)->credentials['access_token'];
    }

    private function stored(int $accountId, IntegrationProvider $provider): Integration
    {
        return $this->repository->findByProvider($accountId, $provider)
            ?? throw IntegrationNotConnectedException::forProvider($provider, $accountId);
    }

    private function renew(Integration $integration): Integration
    {
        if (! $integration->provider->usesGoogleOAuth()) {
            // Meta has no refresh grant: an expired long-lived token can only be replaced
            // by sending the user back through Facebook Login.
            throw IntegrationTokenExpiredException::forProvider($integration->provider);
        }

        $tokens = $this->google->refresh(
            $integration->credentials['refresh_token']
                ?? throw IntegrationTokenExpiredException::forProvider($integration->provider)
        );

        return $this->repository->replaceAccessToken(
            $integration,
            $tokens->accessToken,
            now()->addSeconds($tokens->expiresIn),
        );
    }

    private static function isUsable(Integration $integration): bool
    {
        return isset($integration->credentials['access_token'])
            && ($integration->expires_at === null
                || $integration->expires_at->isAfter(now()->addMinutes(self::REFRESH_SKEW_MINUTES)));
    }
}
