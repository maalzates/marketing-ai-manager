<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Google only returns a refresh token on a grant made with access_type=offline and
 * prompt=consent. Storing the connection without one would silently expire in an hour.
 */
class MissingRefreshTokenException extends ClientException
{
    public static function fromGoogle(array $grantedScopes): self
    {
        $exception = new self(
            'Google no devolvió un permiso permanente. Revoca el acceso de la aplicación en tu cuenta de Google y vuelve a autorizarla.',
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );

        $exception->context = ['granted_scopes' => $grantedScopes];

        return $exception;
    }
}
