<?php

declare(strict_types=1);

namespace App\Modules\Admin\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class AdminUserNotFoundException extends ClientException
{
    public static function withId(int $id): self
    {
        $exception = new self('Ese usuario no existe.', Response::HTTP_NOT_FOUND);
        $exception->context = ['user_id' => $id];

        return $exception;
    }
}
