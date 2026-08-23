<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class AccountNotFoundException extends ClientException
{
    public static function withId(int $id): self
    {
        $exception = new self('Account not found.', Response::HTTP_NOT_FOUND);
        $exception->context = ['account_id' => $id];

        return $exception;
    }
}
