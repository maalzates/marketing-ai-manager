<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class ApifyCreditExhaustedException extends ClientException
{
    /** @param array<string, mixed> $context */
    public static function withContext(array $context): self
    {
        $exception = new self(
            'Tu cuenta de Apify se quedó sin crédito. Recárgala para seguir analizando competidores.',
            Response::HTTP_PAYMENT_REQUIRED,
        );

        $exception->context = $context;

        return $exception;
    }
}
