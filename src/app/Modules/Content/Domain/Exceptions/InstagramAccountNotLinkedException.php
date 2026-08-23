<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class InstagramAccountNotLinkedException extends ClientException
{
    public static function forAccount(int $accountId): self
    {
        $exception = new self(
            'No Instagram professional account is linked to the connected Facebook Pages, or the granted role does not allow publishing.',
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
        $exception->context = ['account_id' => $accountId];

        return $exception;
    }
}
