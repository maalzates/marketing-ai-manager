<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Infrastructure\Clients;

use App\Modules\Core\Domain\Exceptions\ApiCallFailedException;
use App\Modules\Core\Infrastructure\Clients\ApiClientAbstract;
use App\Modules\Integrations\Domain\Enums\VerificationFailure;
use App\Modules\Integrations\Domain\Exceptions\MetaOAuthFailedException;
use App\Modules\Integrations\Domain\Support\VerificationOutcome;
use GuzzleHttp\Client;

class MetaOAuthClient extends ApiClientAbstract
{
    private const string ACCESS_TOKEN_ENDPOINT = 'oauth/access_token';

    private const string ME_ENDPOINT = 'me';

    private const string AD_ACCOUNTS_ENDPOINT = 'me/adaccounts';

    private const string AD_ACCOUNT_FIELDS = 'id,account_id,name,account_status,currency,timezone_name,amount_spent,spend_cap,disable_reason';

    /** @var list<int> Invalid or expired OAuth token, and the generic session error. */
    private const array DEAD_TOKEN_CODES = [102, 190];

    /** @var list<int> Missing scope, missing role on the asset, or no allowlist access. */
    private const array PERMISSION_CODES = [10, 294];

    private const int PERMISSION_CODE_RANGE_START = 200;

    private const int PERMISSION_CODE_RANGE_END = 299;

    public function __construct(
        Client $client,
        private readonly string $graphVersion,
        private readonly string $appId,
        private readonly string $appSecret,
    ) {
        parent::__construct($client);
    }

    /**
     * @throws MetaOAuthFailedException
     */
    public function exchangeCode(string $code, string $redirectUri): array
    {
        return $this->tokenCall([
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);
    }

    /**
     * Meta has no refresh grant: a long-lived token is bought once with a valid
     * short-lived one and simply dies after ~60 days.
     *
     * @throws MetaOAuthFailedException
     */
    public function exchangeForLongLivedToken(string $shortLivedToken): array
    {
        return $this->tokenCall([
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'fb_exchange_token' => $shortLivedToken,
        ]);
    }

    /**
     * @throws MetaOAuthFailedException
     */
    public function me(string $accessToken): array
    {
        try {
            return $this->get($this->versioned(self::ME_ENDPOINT), ['access_token' => $accessToken]);
        } catch (ApiCallFailedException $exception) {
            throw self::translate($exception);
        }
    }

    /**
     * @throws MetaOAuthFailedException
     */
    public function adAccounts(string $accessToken): array
    {
        try {
            return $this->get($this->versioned(self::AD_ACCOUNTS_ENDPOINT), [
                'fields' => self::AD_ACCOUNT_FIELDS,
                'access_token' => $accessToken,
            ]);
        } catch (ApiCallFailedException $exception) {
            throw self::translate($exception);
        }
    }

    public function verify(string $accessToken): VerificationOutcome
    {
        try {
            return VerificationOutcome::valid($this->me($accessToken)['id'] ?? null);
        } catch (MetaOAuthFailedException $exception) {
            return VerificationOutcome::failed(
                self::classify((array) ($exception->getContext()['diagnosis']['body'] ?? [])),
                $exception->getContext()['diagnosis']['status'] ?? null,
                $exception->getContext()['diagnosis'] ?? [],
            );
        }
    }

    /**
     * Meta's own guidance is to branch on the numeric code and never on the message. A
     * permission code is not a bad token: it usually means the person is not yet a tester
     * on the app, or the Page role has not reached them through Business Manager.
     */
    private static function classify(array $body): VerificationFailure
    {
        $code = (int) ($body['error']['code'] ?? 0);

        return match (true) {
            in_array($code, self::DEAD_TOKEN_CODES, true) => VerificationFailure::CREDENTIAL_REJECTED,
            in_array($code, self::PERMISSION_CODES, true) => VerificationFailure::PERMISSION_DENIED,
            $code >= self::PERMISSION_CODE_RANGE_START && $code <= self::PERMISSION_CODE_RANGE_END => VerificationFailure::PERMISSION_DENIED,
            default => VerificationFailure::PROVIDER_UNAVAILABLE,
        };
    }

    /**
     * @throws MetaOAuthFailedException
     */
    private function tokenCall(array $parameters): array
    {
        try {
            return $this->get($this->versioned(self::ACCESS_TOKEN_ENDPOINT), $parameters);
        } catch (ApiCallFailedException $exception) {
            throw self::translate($exception);
        }
    }

    private function versioned(string $endpoint): string
    {
        return "{$this->graphVersion}/{$endpoint}";
    }

    private static function translate(ApiCallFailedException $exception): MetaOAuthFailedException
    {
        return MetaOAuthFailedException::withDiagnosis([
            'status' => $exception->getHttpStatusCode(),
            'body' => $exception->getContext()['response_body'],
        ]);
    }
}
