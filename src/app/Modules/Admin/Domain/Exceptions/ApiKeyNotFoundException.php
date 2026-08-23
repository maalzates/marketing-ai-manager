<?php

declare(strict_types=1);

namespace App\Modules\Admin\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyNotFoundException extends ClientException
{
    public static function withId(int $id): self
    {
        $exception = new self('Esa API key no existe.', Response::HTTP_NOT_FOUND);
        $exception->context = ['api_key_id' => $id];

        return $exception;
    }
}
