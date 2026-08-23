<?php

declare(strict_types=1);

namespace App\Modules\Core\Domain\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class AccountContextException extends ClientException
{
    public static function missingAccount(int $userId): self
    {
        $exception = new self('Your user is not attached to any account.', Response::HTTP_FORBIDDEN);
        $exception->context = ['user_id' => $userId];

        return $exception;
    }

    public static function inactiveAccount(int $accountId): self
    {
        $exception = new self('This account is inactive.', Response::HTTP_FORBIDDEN);
        $exception->context = ['account_id' => $accountId];

        return $exception;
    }

    public static function notResolved(): self
    {
        return new self('No account context is available for this operation.', Response::HTTP_FORBIDDEN);
    }
}
