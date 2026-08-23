<?php

declare(strict_types=1);

namespace App\Modules\Settings\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class InvalidSettingValueException extends ClientException
{
    public static function forKey(string $key, string $expected, string $given): self
    {
        $exception = new self(
            "The setting \"{$key}\" expects a value of type {$expected}, {$given} given.",
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
        $exception->context = ['key' => $key, 'expected' => $expected, 'given' => $given];

        return $exception;
    }
}
