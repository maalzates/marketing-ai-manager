<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Application\Services;

use App\Modules\Integrations\Application\DTO\GoogleTokensDTO;
use App\Modules\Integrations\Application\DTO\OAuthCallbackDTO;
use App\Modules\Integrations\Application\DTO\StoreOAuthCredentialsDTO;
use App\Modules\Integrations\Domain\Contracts\IntegrationRepositoryInterface;
use App\Modules\Integrations\Domain\Enums\IntegrationKind;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use App\Modules\Integrations\Domain\Exceptions\MissingRefreshTokenException;
use App\Modules\Integrations\Domain\Exceptions\OAuthAuthorisationDeniedException;
use App\Modules\Integrations\Domain\Exceptions\UnsupportedIntegrationProviderException;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;

readonly class IntegrationOAuthService
{
    public const string CALLBACK_ROUTE = 'integrations.oauth.callback';

    public function __construct(
        private IntegrationRepositoryInterface $repository,
        private OAuthStateManager $state,
        private GoogleOAuthService $google,
        private MetaOAuthService $meta,
    ) {}

    public function authorisationUrl(int $accountId, IntegrationProvider $provider): string
    {
        $state = $this->state->issue($accountId, $this->oauthCapable($provider));

        return $provider->usesGoogleOAuth()
            ? $this->google->authorisationUrl(self::scopesFor($provider), $state, self::redirectUri($provider))
            : $this->meta->authorisationUrl(self::scopesFor($provider), $state, self::redirectUri($provider));
    }

    public function completeCallback(OAuthCallbackDTO $dto): Integration
    {
        if ($dto->error !== null) {
            throw OAuthAuthorisationDeniedException::forProvider($dto->provider, $dto->error);
        }

        $accountId = $this->state->consume($dto->state, $this->oauthCapable($dto->provider));

        return $this->repository->storeOAuthCredentials(
            $dto->provider->usesGoogleOAuth()
                ? $this->googleCredentials($accountId, $dto)
                : $this->metaCredentials($accountId, $dto)
        );
    }

    private function googleCredentials(int $accountId, OAuthCallbackDTO $dto): StoreOAuthCredentialsDTO
    {
        $tokens = $this->google->exchangeCode((string) $dto->code, self::redirectUri($dto->provider));

        return new StoreOAuthCredentialsDTO(
            $accountId,
            $dto->provider,
            self::googleCredentialSet($tokens),
            $tokens->scopes,
            now()->addSeconds($tokens->expiresIn),
            $this->google->userInfo($tokens->accessToken)['sub'] ?? null,
        );
    }

    private function metaCredentials(int $accountId, OAuthCallbackDTO $dto): StoreOAuthCredentialsDTO
    {
        $tokens = $this->meta->exchangeForLongLivedToken(
            $this->meta->exchangeCode((string) $dto->code, self::redirectUri($dto->provider))->accessToken
        );

        return new StoreOAuthCredentialsDTO(
            $accountId,
            $dto->provider,
            ['access_token' => $tokens->accessToken, 'token_type' => $tokens->tokenType],
            self::scopesFor($dto->provider),
            $tokens->expiresIn === null ? null : now()->addSeconds($tokens->expiresIn),
            $this->meta->me($tokens->accessToken)['id'] ?? null,
        );
    }

    /** @return array<string, string> */
    private static function googleCredentialSet(GoogleTokensDTO $tokens): array
    {
        return [
            'access_token' => $tokens->accessToken,
            'refresh_token' => $tokens->refreshToken ?? throw MissingRefreshTokenException::fromGoogle($tokens->scopes),
            'token_type' => $tokens->tokenType,
        ];
    }

    private function oauthCapable(IntegrationProvider $provider): IntegrationProvider
    {
        return match (true) {
            $provider->kind() !== IntegrationKind::OAUTH => throw UnsupportedIntegrationProviderException::forOAuth($provider),
            $provider === IntegrationProvider::TIKTOK => throw UnsupportedIntegrationProviderException::notImplemented($provider),
            default => $provider,
        };
    }

    /**
     * Derived from the route rather than read from config: this is the URI the provider will
     * actually call back on, and it must be byte-identical in the authorisation and the
     * code exchange. Both values still have to be registered in the provider's console.
     */
    private static function redirectUri(IntegrationProvider $provider): string
    {
        return route(self::CALLBACK_ROUTE, ['provider' => $provider->value]);
    }

    /** @return list<string> */
    private static function scopesFor(IntegrationProvider $provider): array
    {
        return match ($provider) {
            IntegrationProvider::GOOGLE => [
                ...config('services.google.login_scopes'),
                ...config('services.google.drive_scopes'),
            ],
            IntegrationProvider::YOUTUBE => config('services.google.youtube_scopes'),
            default => config('services.meta.scopes'),
        };
    }
}
