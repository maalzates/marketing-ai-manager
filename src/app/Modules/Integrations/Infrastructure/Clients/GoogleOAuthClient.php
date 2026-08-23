<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Infrastructure\Clients;

use App\Modules\Core\Domain\Exceptions\ApiCallFailedException;
use App\Modules\Core\Infrastructure\Clients\ApiClientAbstract;
use App\Modules\Integrations\Domain\Enums\VerificationFailure;
use App\Modules\Integrations\Domain\Exceptions\GoogleOAuthFailedException;
use App\Modules\Integrations\Domain\Support\VerificationOutcome;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Symfony\Component\HttpFoundation\Response;

/**
 * The three Google OAuth endpoints live on three different hosts, so they arrive as
 * absolute URLs from config instead of as paths under a shared base URI.
 */
class GoogleOAuthClient extends ApiClientAbstract
{
    private const string REVOKED_GRANT_ERROR = 'invalid_grant';

    public function __construct(
        Client $client,
        private readonly string $tokenUrl,
        private readonly string $revokeUrl,
        private readonly string $userInfoUrl,
        private readonly string $clientId = '',
        private readonly string $clientSecret = '',
    ) {
        parent::__construct($client);
    }

    /**
     * @throws GoogleOAuthFailedException
     */
    public function exchangeCode(string $code, string $redirectUri): array
    {
        return $this->token([
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);
    }

    /**
     * Null rather than an exception when the grant is dead: the daily health job has to
     * mark the connection expired, and only a client may catch.
     *
     * @throws GoogleOAuthFailedException
     */
    public function refresh(string $refreshToken): ?array
    {
        try {
            return $this->token([
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);
        } catch (GoogleOAuthFailedException $exception) {
            return self::isRevokedGrant($exception) ? null : throw $exception;
        }
    }

    /**
     * @throws GoogleOAuthFailedException
     */
    public function userInfo(): array
    {
        try {
            return $this->get($this->userInfoUrl);
        } catch (ApiCallFailedException $exception) {
            throw self::translate($exception);
        }
    }

    public function verify(): VerificationOutcome
    {
        try {
            return VerificationOutcome::valid($this->userInfo()['sub'] ?? null);
        } catch (GoogleOAuthFailedException $exception) {
            return VerificationOutcome::failed(
                self::classify((int) ($exception->getContext()['diagnosis']['status'] ?? 0)),
                $exception->getContext()['diagnosis']['status'] ?? null,
                $exception->getContext()['diagnosis'] ?? [],
            );
        }
    }

    private static function classify(int $httpStatus): VerificationFailure
    {
        return match ($httpStatus) {
            Response::HTTP_UNAUTHORIZED => VerificationFailure::CREDENTIAL_REJECTED,
            Response::HTTP_FORBIDDEN => VerificationFailure::PERMISSION_DENIED,
            default => VerificationFailure::PROVIDER_UNAVAILABLE,
        };
    }

    /**
     * @throws GoogleOAuthFailedException
     */
    public function revoke(string $token): void
    {
        try {
            $this->post($this->revokeUrl, [RequestOptions::FORM_PARAMS => ['token' => $token]]);
        } catch (ApiCallFailedException $exception) {
            throw self::translate($exception);
        }
    }

    /**
     * @throws GoogleOAuthFailedException
     */
    private function token(array $parameters): array
    {
        try {
            return $this->post($this->tokenUrl, [RequestOptions::FORM_PARAMS => $parameters]);
        } catch (ApiCallFailedException $exception) {
            throw self::translate($exception);
        }
    }

    private static function translate(ApiCallFailedException $exception): GoogleOAuthFailedException
    {
        return GoogleOAuthFailedException::withDiagnosis([
            'status' => $exception->getHttpStatusCode(),
            'body' => $exception->getContext()['response_body'],
        ]);
    }

    private static function isRevokedGrant(GoogleOAuthFailedException $exception): bool
    {
        return ($exception->getContext()['diagnosis']['body']['error'] ?? null) === self::REVOKED_GRANT_ERROR;
    }
}
