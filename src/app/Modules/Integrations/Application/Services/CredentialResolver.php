<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Application\Services;

use App\Modules\Integrations\Domain\Contracts\IntegrationRepositoryInterface;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use App\Modules\Integrations\Domain\Enums\IntegrationStatus;
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

    private const string DEAD_GRANT_REASON = 'refresh_rejected';

    private const string NO_REFRESH_GRANT_REASON = 'provider_has_no_refresh_grant';

    public function __construct(
        private IntegrationRepositoryInterface $repository,
        private IntegrationService $integrations,
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
        // Meta has no refresh grant, and Google cannot mint an access token without the
        // stored refresh token: both only come back through a fresh consent.
        if (! $integration->provider->usesGoogleOAuth() || ! isset($integration->credentials['refresh_token'])) {
            $this->expire($integration, self::NO_REFRESH_GRANT_REASON);
        }

        $tokens = $this->google->tryRefresh($integration->credentials['refresh_token']);

        if ($tokens === null) {
            $this->expire($integration, self::DEAD_GRANT_REASON);
        }

        return $this->repository->replaceAccessToken(
            $integration,
            $tokens->accessToken,
            now()->addSeconds($tokens->expiresIn),
        );
    }

    /**
     * The status is what `CheckIntegrationHealthJob`, the health checklist and the UI
     * banner read, so a grant the caller just found dead is persisted before the caller
     * is told: otherwise nothing ever warns the account it has to re-authorise.
     */
    private function expire(Integration $integration, string $reason): never
    {
        $this->integrations->markFailure($integration, IntegrationStatus::EXPIRED, ['reason' => $reason]);

        throw IntegrationTokenExpiredException::forProvider($integration->provider, [
            'integration_id' => $integration->id,
            'reason' => $reason,
        ]);
    }

    private static function isUsable(Integration $integration): bool
    {
        return isset($integration->credentials['access_token'])
            && ($integration->expires_at === null
                || $integration->expires_at->isAfter(now()->addMinutes(self::REFRESH_SKEW_MINUTES)));
    }
}
