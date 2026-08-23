<?php

declare(strict_types=1);

namespace App\Modules\Ai\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class UnknownLlmModelException extends ClientException
{
    public static function withModel(string $model): self
    {
        $exception = new self(
            sprintf('The model "%s" is not one this application knows how to call. Pick another in Settings → Models.', $model),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );

        $exception->context = ['model' => $model];

        return $exception;
    }
}
