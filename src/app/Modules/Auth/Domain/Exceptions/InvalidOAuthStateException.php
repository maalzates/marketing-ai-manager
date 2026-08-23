<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class InvalidOAuthStateException extends ClientException
{
    private const string MESSAGE = 'El inicio de sesión no se pudo correlacionar. Vuelve a intentarlo desde el principio.';

    public static function rejected(string $reason): self
    {
        $exception = new self(self::MESSAGE, Response::HTTP_UNPROCESSABLE_ENTITY);
        $exception->context = ['reason' => $reason];

        return $exception;
    }
}
