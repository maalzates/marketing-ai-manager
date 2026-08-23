<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Domain\Exceptions;

use App\Modules\Competitors\Domain\Enums\CompetitorPlatform;
use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class UnsupportedCompetitorPlatformException extends ClientException
{
    public static function forPlatform(CompetitorPlatform $platform): self
    {
        $exception = new self(
            "Todavía no podemos analizar competidores de {$platform->value}.",
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );

        $exception->context = ['platform' => $platform->value];

        return $exception;
    }
}
