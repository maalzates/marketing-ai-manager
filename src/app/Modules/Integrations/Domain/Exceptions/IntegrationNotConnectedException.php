<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use Symfony\Component\HttpFoundation\Response;

class IntegrationNotConnectedException extends ClientException
{
    public static function forProvider(IntegrationProvider $provider, int $accountId): self
    {
        $exception = new self(
            "No has conectado {$provider->label()} todavía. Configúralo en Integraciones.",
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );

        $exception->context = ['account_id' => $accountId, 'provider' => $provider->value];

        return $exception;
    }
}
