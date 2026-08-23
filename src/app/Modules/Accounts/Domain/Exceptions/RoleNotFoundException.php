<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class RoleNotFoundException extends ClientException
{
    public static function withId(int $id): self
    {
        $exception = new self('Role not found.', Response::HTTP_NOT_FOUND);
        $exception->context = ['role_id' => $id];

        return $exception;
    }

    public static function withName(string $name): self
    {
        $exception = new self('Role not found.', Response::HTTP_NOT_FOUND);
        $exception->context = ['role' => $name];

        return $exception;
    }
}
