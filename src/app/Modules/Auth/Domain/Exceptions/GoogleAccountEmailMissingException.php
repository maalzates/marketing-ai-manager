<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class GoogleAccountEmailMissingException extends ClientException
{
    private const string MESSAGE = 'Tu cuenta de Google no expone un email. Concede el permiso de email y vuelve a intentarlo.';

    public static function forSubject(string $googleId): self
    {
        $exception = new self(self::MESSAGE, Response::HTTP_UNPROCESSABLE_ENTITY);
        $exception->context = ['google_id' => $googleId];

        return $exception;
    }
}
