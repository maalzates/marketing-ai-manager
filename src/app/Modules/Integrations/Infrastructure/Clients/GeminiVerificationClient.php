<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Infrastructure\Clients;

use App\Modules\Core\Domain\Exceptions\ApiCallFailedException;
use App\Modules\Integrations\Domain\Enums\VerificationFailure;
use App\Modules\Integrations\Domain\Support\VerificationOutcome;
use Symfony\Component\HttpFoundation\Response;

class GeminiVerificationClient extends VerificationClientAbstract
{
    private const string MODELS_ENDPOINT = 'models';

    /** @var list<string> */
    private const array INVALID_KEY_REASONS = ['API_KEY_INVALID', 'ACCESS_TOKEN_TYPE_UNSUPPORTED'];

    private const string UNAUTHENTICATED_STATUS = 'UNAUTHENTICATED';

    private const string PERMISSION_DENIED_STATUS = 'PERMISSION_DENIED';

    public function verify(): VerificationOutcome
    {
        try {
            $this->get(self::MODELS_ENDPOINT, ['pageSize' => 1]);

            return VerificationOutcome::valid();
        } catch (ApiCallFailedException $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Gemini answers an invalid key with 400 INVALID_ARGUMENT, not 401, so the status is
     * useless here: `error.details[].reason` is the only reliable signal.
     */
    protected function classify(array $body, int $httpStatus): VerificationFailure
    {
        return match (true) {
            self::hasInvalidKeyReason($body) => VerificationFailure::CREDENTIAL_REJECTED,
            ($body['error']['status'] ?? null) === self::UNAUTHENTICATED_STATUS => VerificationFailure::CREDENTIAL_REJECTED,
            $httpStatus === Response::HTTP_UNAUTHORIZED => VerificationFailure::CREDENTIAL_REJECTED,
            ($body['error']['status'] ?? null) === self::PERMISSION_DENIED_STATUS => VerificationFailure::PERMISSION_DENIED,
            default => VerificationFailure::PROVIDER_UNAVAILABLE,
        };
    }

    private static function hasInvalidKeyReason(array $body): bool
    {
        return collect($body['error']['details'] ?? [])
            ->contains(fn (mixed $detail): bool => in_array(
                is_array($detail) ? ($detail['reason'] ?? null) : null,
                self::INVALID_KEY_REASONS,
                true,
            ));
    }
}
