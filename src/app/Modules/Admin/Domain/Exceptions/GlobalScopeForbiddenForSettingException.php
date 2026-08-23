<?php

declare(strict_types=1);

namespace App\Modules\Admin\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class GlobalScopeForbiddenForSettingException extends ClientException
{
    /** @param list<string> $keys */
    public static function forKeys(array $keys): self
    {
        $exception = new self(
            'Estos ajustes identifican una cuenta externa concreta y solo pueden escribirse '
            .'por cuenta: '.implode(', ', $keys).'. Indica un account_id.',
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );

        $exception->context = ['keys' => $keys];
        $exception->extras = ['account_only_keys' => $keys];

        return $exception;
    }
}
