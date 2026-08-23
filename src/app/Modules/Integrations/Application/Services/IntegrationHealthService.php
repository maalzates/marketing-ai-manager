<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Application\Services;

use App\Modules\Integrations\Domain\Contracts\IntegrationRepositoryInterface;
use App\Modules\Integrations\Domain\Enums\IntegrationStatus;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;

readonly class IntegrationHealthService
{
    private const int EXPIRY_HORIZON_DAYS = 7;

    private const string DEAD_GRANT_REASON = 'refresh_rejected';

    private const string NO_REFRESH_GRANT_REASON = 'provider_has_no_refresh_grant';

    public function __construct(
        private IntegrationRepositoryInterface $repository,
        private IntegrationService $integrations,
        private GoogleOAuthService $google,
    ) {}

    /** Accounts without OAuth integrations never reach the loop, so they cost one query. */
    public function renewExpiringEverywhere(): void
    {
        $this->repository->accountIdsWithOAuthIntegrations()
            ->each(fn (int $accountId) => $this->renewExpiring($accountId));
    }

    public function renewExpiring(int $accountId): void
    {
        $this->repository
            ->oauthExpiringFor($accountId, now()->addDays(self::EXPIRY_HORIZON_DAYS))
            // An already-expired grant is not retried: nothing but a fresh consent revives it.
            ->reject(fn (Integration $integration): bool => $integration->status === IntegrationStatus::EXPIRED)
            ->each(fn (Integration $integration) => $this->renew($integration));
    }

    private function renew(Integration $integration): void
    {
        if (! $integration->provider->usesGoogleOAuth()) {
            $this->expire($integration, self::NO_REFRESH_GRANT_REASON);

            return;
        }

        $tokens = $this->google->tryRefresh((string) ($integration->credentials['refresh_token'] ?? ''));

        if ($tokens === null) {
            $this->expire($integration, self::DEAD_GRANT_REASON);

            return;
        }

        $this->repository->replaceAccessToken(
            $integration,
            $tokens->accessToken,
            now()->addSeconds($tokens->expiresIn),
        );
    }

    private function expire(Integration $integration, string $reason): void
    {
        $this->integrations->markFailure($integration, IntegrationStatus::EXPIRED, ['reason' => $reason]);
    }
}
