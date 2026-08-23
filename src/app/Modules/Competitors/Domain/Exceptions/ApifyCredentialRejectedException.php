<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class ApifyCredentialRejectedException extends ClientException
{
    /** @param array<string, mixed> $context */
    public static function withContext(array $context): self
    {
        $exception = new self(
            'Apify rechazó tu token. Vuelve a conectarlo en Integraciones.',
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );

        $exception->context = $context;

        return $exception;
    }
}
