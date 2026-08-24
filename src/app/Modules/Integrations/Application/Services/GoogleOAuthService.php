<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Application\Services;

use App\Modules\Integrations\Application\DTO\GoogleTokensDTO;
use App\Modules\Integrations\Domain\Contracts\GoogleOAuthClientFactoryInterface;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use App\Modules\Integrations\Domain\Exceptions\IntegrationTokenExpiredException;
use App\Modules\Integrations\Domain\Exceptions\OAuthClientNotConfiguredException;
use App\Modules\Integrations\Domain\Support\VerificationOutcome;

/**
 * One grant covers sign-in, Drive and YouTube: the flow is identical and only the
 * requested scopes change, so the Auth module drives this same Service for Google login.
 */
readonly class GoogleOAuthService
{
    public function __construct(private GoogleOAuthClientFactoryInterface $clients) {}

    /**
     * @param  list<string>  $scopes
     */
    public function authorisationUrl(array $scopes, string $state, ?string $redirectUri = null): string
    {
        return config('services.google.auth_url').'?'.http_build_query([
            'client_id' => self::clientId(),
            'redirect_uri' => $redirectUri ?? config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            // Without both of these Google returns no refresh token on a repeat grant, and
            // the connection silently dies an hour later.
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code, ?string $redirectUri = null): GoogleTokensDTO
    {
        return self::toTokens($this->clients->create()->exchangeCode(
            $code,
            $redirectUri ?? config('services.google.redirect'),
        ));
    }

    public function refresh(string $refreshToken): GoogleTokensDTO
    {
        return $this->tryRefresh($refreshToken)
            ?? throw IntegrationTokenExpiredException::forProvider(IntegrationProvider::GOOGLE);
    }

    /** Null when Google answered `invalid_grant` — the grant is gone and no retry helps. */
    public function tryRefresh(string $refreshToken): ?GoogleTokensDTO
    {
        return ($tokens = $this->clients->create()->refresh($refreshToken)) === null
            ? null
            : self::toTokens($tokens);
    }

    public function userInfo(string $accessToken): array
    {
        return $this->clients->forAccessToken($accessToken)->userInfo();
    }

    public function verify(string $accessToken): VerificationOutcome
    {
        return $this->clients->forAccessToken($accessToken)->verify();
    }

    public function revoke(string $token): void
    {
        $this->clients->create()->revoke($token);
    }

    private static function clientId(): string
    {
        return (string) (config('services.google.client_id')
            ?: throw OAuthClientNotConfiguredException::missing('GOOGLE_CLIENT_ID'));
    }

    private static function toTokens(array $response): GoogleTokensDTO
    {
        return new GoogleTokensDTO(
            (string) ($response['access_token'] ?? ''),
            $response['refresh_token'] ?? null,
            (int) ($response['expires_in'] ?? 0),
            (string) ($response['token_type'] ?? 'Bearer'),
            $response['id_token'] ?? null,
            array_values(array_filter(explode(' ', (string) ($response['scope'] ?? '')))),
        );
    }
}
