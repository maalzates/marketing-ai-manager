<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Application\Services;

use App\Modules\Integrations\Application\DTO\MetaTokensDTO;
use App\Modules\Integrations\Domain\Contracts\MetaOAuthClientFactoryInterface;
use App\Modules\Integrations\Domain\Support\VerificationOutcome;

readonly class MetaOAuthService
{
    public function __construct(private MetaOAuthClientFactoryInterface $clients) {}

    /**
     * @param  list<string>  $scopes
     */
    public function authorisationUrl(array $scopes, string $state, ?string $redirectUri = null): string
    {
        return config('services.meta.auth_url').'?'.http_build_query([
            'client_id' => config('services.meta.app_id'),
            'redirect_uri' => $redirectUri ?? config('services.meta.redirect'),
            'response_type' => 'code',
            'scope' => implode(',', $scopes),
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code, ?string $redirectUri = null): MetaTokensDTO
    {
        return self::toTokens($this->clients->create()->exchangeCode(
            $code,
            $redirectUri ?? config('services.meta.redirect'),
        ));
    }

    public function exchangeForLongLivedToken(string $shortLivedToken): MetaTokensDTO
    {
        return self::toTokens($this->clients->create()->exchangeForLongLivedToken($shortLivedToken));
    }

    public function me(string $accessToken): array
    {
        return $this->clients->create()->me($accessToken);
    }

    public function adAccounts(string $accessToken): array
    {
        return $this->clients->create()->adAccounts($accessToken)['data'] ?? [];
    }

    public function verify(string $accessToken): VerificationOutcome
    {
        return $this->clients->create()->verify($accessToken);
    }

    private static function toTokens(array $response): MetaTokensDTO
    {
        return new MetaTokensDTO(
            (string) ($response['access_token'] ?? ''),
            (string) ($response['token_type'] ?? 'bearer'),
            isset($response['expires_in']) ? (int) $response['expires_in'] : null,
        );
    }
}
