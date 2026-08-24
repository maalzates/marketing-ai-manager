<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ApiCallFailedException;
use App\Modules\Core\Domain\Exceptions\ClientException;
use App\Modules\Core\Domain\Support\SecretMasker;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Meta writes two messages per error: `error_user_msg` is meant for the advertiser and is
 * safe to surface, while `message` is developer-facing and changes without notice. Only
 * the first ever becomes the client message; the rest — including `fbtrace_id`, the one
 * thing Meta support accepts on an escalation — stays in the context.
 */
class MetaAdsClientException extends ClientException
{
    private const string FALLBACK_MESSAGE = 'La plataforma de anuncios rechazó la operación.';

    public static function fromApiCallFailedException(ApiCallFailedException $exception): static
    {
        $error = $exception->getContext()['response_body']['error'] ?? [];

        $translated = new self(
            is_string($error['error_user_msg'] ?? null) ? $error['error_user_msg'] : self::FALLBACK_MESSAGE,
            $exception->getHttpStatusCode(),
            $exception,
        );

        $translated->context = (new SecretMasker)->mask($exception->getContext() + [
            'meta_error_code' => $error['code'] ?? null,
            'meta_error_subcode' => $error['error_subcode'] ?? null,
            'meta_error_type' => $error['type'] ?? null,
            'fbtrace_id' => $error['fbtrace_id'] ?? null,
        ]);

        return $translated;
    }

    /** The piece could not be read from Drive at all, so Meta was never asked. */
    public static function mediaUnreadable(string $endpoint, string $filename, Throwable $previous): self
    {
        $exception = new self(
            'No se pudo leer la pieza desde la biblioteca para subirla a la plataforma.',
            Response::HTTP_BAD_GATEWAY,
            $previous,
        );

        $exception->context = ['endpoint' => $endpoint, 'filename' => $filename];

        return $exception;
    }

    public static function unexpectedResponse(string $endpoint, array $response): self
    {
        $exception = new self(self::FALLBACK_MESSAGE, Response::HTTP_BAD_GATEWAY);
        $exception->context = ['endpoint' => $endpoint, 'response' => (new SecretMasker)->mask($response)];

        return $exception;
    }
}
