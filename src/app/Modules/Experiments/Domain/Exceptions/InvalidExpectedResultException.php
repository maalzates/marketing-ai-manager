<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class InvalidExpectedResultException extends ClientException
{
    public static function malformed(array $expectedResult): self
    {
        $exception = new self(
            'El resultado esperado debe ser {"metric": "...", "operator": "lte|gte", "value": número}.',
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
        $exception->context = ['expected_result' => $expectedResult];

        return $exception;
    }
}
