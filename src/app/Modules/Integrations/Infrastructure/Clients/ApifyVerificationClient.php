<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Infrastructure\Clients;

use App\Modules\Core\Domain\Exceptions\ApiCallFailedException;
use App\Modules\Integrations\Domain\Enums\VerificationFailure;
use App\Modules\Integrations\Domain\Support\VerificationOutcome;

class ApifyVerificationClient extends VerificationClientAbstract
{
    private const string CURRENT_USER_ENDPOINT = 'users/me';

    /** @var list<string> */
    private const array INVALID_TOKEN_TYPES = ['invalid-token', 'missing-api-token', 'user-not-logged-in'];

    /** @var list<string> */
    private const array PLAN_LIMIT_TYPES = ['insufficient-credit', 'not-enough-usage-to-run-paid-actor', 'x402-payment-required'];

    public function verify(): VerificationOutcome
    {
        try {
            return VerificationOutcome::valid($this->get(self::CURRENT_USER_ENDPOINT)['data']['id'] ?? null);
        } catch (ApiCallFailedException $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Apify puts several failures under 400 that a reader would expect elsewhere —
     * `actor-not-found` among them — so `error.type` is the only thing worth matching.
     */
    protected function classify(array $body, int $httpStatus): VerificationFailure
    {
        return match (true) {
            in_array($body['error']['type'] ?? null, self::INVALID_TOKEN_TYPES, true) => VerificationFailure::CREDENTIAL_REJECTED,
            in_array($body['error']['type'] ?? null, self::PLAN_LIMIT_TYPES, true) => VerificationFailure::PERMISSION_DENIED,
            default => VerificationFailure::PROVIDER_UNAVAILABLE,
        };
    }
}
