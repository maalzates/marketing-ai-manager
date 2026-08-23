<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Infrastructure\Clients;

use App\Modules\Core\Domain\Exceptions\ApiCallFailedException;
use App\Modules\Integrations\Domain\Enums\VerificationFailure;
use App\Modules\Integrations\Domain\Support\VerificationOutcome;
use Symfony\Component\HttpFoundation\Response;

class OpenAiVerificationClient extends VerificationClientAbstract
{
    private const string MODELS_ENDPOINT = 'models';

    private const string INVALID_KEY_CODE = 'invalid_api_key';

    private const string OUT_OF_QUOTA_CODE = 'insufficient_quota';

    public function verify(): VerificationOutcome
    {
        try {
            $this->get(self::MODELS_ENDPOINT);

            return VerificationOutcome::valid();
        } catch (ApiCallFailedException $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * OpenAI labels a 401 as `invalid_request_error`, so `error.type` classifies nothing
     * here — only `error.code` does.
     */
    protected function classify(array $body, int $httpStatus): VerificationFailure
    {
        return match (true) {
            ($body['error']['code'] ?? null) === self::INVALID_KEY_CODE => VerificationFailure::CREDENTIAL_REJECTED,
            $httpStatus === Response::HTTP_UNAUTHORIZED => VerificationFailure::CREDENTIAL_REJECTED,
            ($body['error']['code'] ?? null) === self::OUT_OF_QUOTA_CODE => VerificationFailure::PERMISSION_DENIED,
            $httpStatus === Response::HTTP_FORBIDDEN => VerificationFailure::PERMISSION_DENIED,
            default => VerificationFailure::PROVIDER_UNAVAILABLE,
        };
    }
}
