<?php

declare(strict_types=1);

namespace App\Modules\Core\Domain\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class RoleNotAllowedException extends ClientException
{
    /**
     * @param  list<string>  $roles
     */
    public static function forRoles(array $roles): self
    {
        $exception = new self('You are not allowed to perform this action.', Response::HTTP_FORBIDDEN);
        $exception->context = ['required_roles' => $roles];

        return $exception;
    }
}
