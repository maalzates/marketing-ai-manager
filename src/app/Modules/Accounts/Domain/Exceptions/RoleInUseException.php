<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class RoleInUseException extends ClientException
{
    public static function withId(int $id): self
    {
        $exception = new self('This role is still assigned to at least one user.', Response::HTTP_CONFLICT);
        $exception->context = ['role_id' => $id];

        return $exception;
    }
}
