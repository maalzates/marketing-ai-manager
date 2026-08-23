<?php

declare(strict_types=1);

namespace App\Modules\Chat\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class ChatDisabledException extends ClientException
{
    public static function forAccount(int $accountId): self
    {
        $exception = new self('The assistant is disabled for this account.', Response::HTTP_FORBIDDEN);
        $exception->context = ['account_id' => $accountId];

        return $exception;
    }
}
