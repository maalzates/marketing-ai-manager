<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class InvalidOAuthStateException extends ClientException
{
    private const string MESSAGE = 'La autorización no se pudo correlacionar. Vuelve a iniciarla desde Integraciones.';

    public static function rejected(string $reason): self
    {
        $exception = new self(self::MESSAGE, Response::HTTP_UNPROCESSABLE_ENTITY);
        $exception->context = ['reason' => $reason];

        return $exception;
    }
}
