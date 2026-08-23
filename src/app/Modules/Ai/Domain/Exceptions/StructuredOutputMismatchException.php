<?php

declare(strict_types=1);

namespace App\Modules\Ai\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class StructuredOutputMismatchException extends ClientException
{
    /** @param  list<string>  $violations */
    public static function withViolations(string $model, array $violations, ?array $received): self
    {
        $exception = new self(
            'The model returned an answer that does not match the expected structure. Try again.',
            Response::HTTP_BAD_GATEWAY,
        );

        $exception->context = ['model' => $model, 'violations' => $violations, 'received' => $received];

        return $exception;
    }
}
