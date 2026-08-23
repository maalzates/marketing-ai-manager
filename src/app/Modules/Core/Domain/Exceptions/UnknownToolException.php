<?php

declare(strict_types=1);

namespace App\Modules\Core\Domain\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class UnknownToolException extends ClientException
{
    public static function withName(string $name): self
    {
        $exception = new self('Unknown tool requested.', Response::HTTP_NOT_FOUND);
        $exception->context = ['tool' => $name];

        return $exception;
    }
}
