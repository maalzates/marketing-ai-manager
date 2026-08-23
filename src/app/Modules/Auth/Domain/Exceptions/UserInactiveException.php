<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class UserInactiveException extends ClientException
{
    private const string MESSAGE = 'Tu usuario está desactivado. Contacta con un administrador.';

    public static function withId(int $userId): self
    {
        $exception = new self(self::MESSAGE, Response::HTTP_FORBIDDEN);
        $exception->context = ['user_id' => $userId];

        return $exception;
    }
}
