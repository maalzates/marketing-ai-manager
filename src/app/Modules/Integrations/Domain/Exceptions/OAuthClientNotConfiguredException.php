<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * An empty client id still builds a perfectly valid URL, so without this the first sign
 * anything is wrong is Google answering `invalid_request` to the user. Failing here names
 * the variable instead.
 */
class OAuthClientNotConfiguredException extends ApiException
{
    public static function missing(string $variable): self
    {
        $exception = new self(
            "La aplicación no tiene configurada la credencial de OAuth. Falta la variable {$variable}.",
            Response::HTTP_INTERNAL_SERVER_ERROR,
        );
        $exception->context = ['variable' => $variable];

        return $exception;
    }
}
