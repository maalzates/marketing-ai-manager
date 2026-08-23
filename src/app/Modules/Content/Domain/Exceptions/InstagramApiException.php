<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ApiCallFailedException;
use App\Modules\Core\Domain\Exceptions\ClientException;
use App\Modules\Core\Domain\Support\SecretMasker;
use Symfony\Component\HttpFoundation\Response;

/**
 * Meta answers 400 for most failures and puts the real cause in `error.code`, so the HTTP
 * status says nothing about whether retrying is worth it. `is_transient` and the retryable
 * code list decide that here, once, for every caller.
 */
class InstagramApiException extends ClientException
{
    /** Codes Meta documents as worth retrying: throttling and temporary unavailability. */
    private const array RETRYABLE_CODES = [1, 2, 4, 17, 32, 341, 613];

    private bool $transient = false;

    public static function fromApiCall(ApiCallFailedException $exception, string $operation): self
    {
        $error = $exception->getContext()['response_body']['error'] ?? [];

        $failure = new self(
            $error['error_user_msg'] ?? $error['message'] ?? 'Instagram rejected the request.',
            Response::HTTP_BAD_GATEWAY,
            $exception,
        );

        $failure->transient = ($error['is_transient'] ?? false) === true
            || in_array($error['code'] ?? 0, self::RETRYABLE_CODES, true);

        // The wrapped context carries the outbound request options; nothing reaches a log
        // unmasked. fbtrace_id is kept because Meta support cannot investigate without it.
        $failure->context = (new SecretMasker)->mask([
            'operation' => $operation,
            'meta_error_code' => $error['code'] ?? null,
            'meta_error_subcode' => $error['error_subcode'] ?? null,
            'fbtrace_id' => $error['fbtrace_id'] ?? null,
            'is_transient' => $failure->transient,
            ...$exception->getContext(),
        ]);

        return $failure;
    }

    public function isTransient(): bool
    {
        return $this->transient;
    }
}
