<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class AccountInactiveException extends ClientException
{
    public static function withId(int $id): self
    {
        $exception = new self('This account is inactive.', Response::HTTP_FORBIDDEN);
        $exception->context = ['account_id' => $id];

        return $exception;
    }
}
