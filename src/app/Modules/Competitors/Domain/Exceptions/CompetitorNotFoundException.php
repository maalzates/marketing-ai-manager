<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class CompetitorNotFoundException extends ClientException
{
    public static function withId(int $id): self
    {
        $exception = new self('No encontramos ese competidor.', Response::HTTP_NOT_FOUND);
        $exception->context = ['competitor_id' => $id];

        return $exception;
    }
}
