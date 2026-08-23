<?php

declare(strict_types=1);

namespace App\Modules\Settings\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class UnknownSettingKeyException extends ClientException
{
    public static function forKey(string $key): self
    {
        $exception = new self(
            "The setting \"{$key}\" is not part of the settings registry.",
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
        $exception->context = ['key' => $key];

        return $exception;
    }
}
