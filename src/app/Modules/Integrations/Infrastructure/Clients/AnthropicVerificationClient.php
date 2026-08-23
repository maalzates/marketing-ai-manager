<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Infrastructure\Clients;

use App\Modules\Core\Domain\Exceptions\ApiCallFailedException;
use App\Modules\Integrations\Domain\Enums\VerificationFailure;
use App\Modules\Integrations\Domain\Support\VerificationOutcome;

class AnthropicVerificationClient extends VerificationClientAbstract
{
    private const string MODELS_ENDPOINT = 'v1/models';

    private const string INVALID_KEY_TYPE = 'authentication_error';

    /** @var list<string> */
    private const array ACCOUNT_BLOCKED_TYPES = ['permission_error', 'billing_error'];

    public function verify(): VerificationOutcome
    {
        try {
            $this->get(self::MODELS_ENDPOINT, ['limit' => 1]);

            return VerificationOutcome::valid();
        } catch (ApiCallFailedException $exception) {
            return $this->failed($exception);
        }
    }

    /** Anthropic documents `error.type`; the message string is explicitly not stable. */
    protected function classify(array $body, int $httpStatus): VerificationFailure
    {
        return match (true) {
            ($body['error']['type'] ?? null) === self::INVALID_KEY_TYPE => VerificationFailure::CREDENTIAL_REJECTED,
            in_array($body['error']['type'] ?? null, self::ACCOUNT_BLOCKED_TYPES, true) => VerificationFailure::PERMISSION_DENIED,
            default => VerificationFailure::PROVIDER_UNAVAILABLE,
        };
    }
}
