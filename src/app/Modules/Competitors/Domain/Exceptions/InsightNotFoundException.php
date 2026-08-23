<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class InsightNotFoundException extends ClientException
{
    public static function withId(int $id): self
    {
        $exception = new self('No encontramos ese insight.', Response::HTTP_NOT_FOUND);
        $exception->context = ['insight_id' => $id];

        return $exception;
    }
}
