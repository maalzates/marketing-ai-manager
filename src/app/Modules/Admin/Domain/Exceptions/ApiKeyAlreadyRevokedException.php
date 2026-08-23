<?php

declare(strict_types=1);

namespace App\Modules\Admin\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAlreadyRevokedException extends ClientException
{
    public static function withPrefix(string $prefix): self
    {
        $exception = new self("La API key {$prefix}… ya estaba revocada.", Response::HTTP_CONFLICT);
        $exception->context = ['prefix' => $prefix];

        return $exception;
    }
}
