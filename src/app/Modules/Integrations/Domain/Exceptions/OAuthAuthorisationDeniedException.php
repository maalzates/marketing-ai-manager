<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use Symfony\Component\HttpFoundation\Response;

class OAuthAuthorisationDeniedException extends ClientException
{
    public static function forProvider(IntegrationProvider $provider, string $error): self
    {
        $exception = new self(
            "No autorizaste el acceso a {$provider->label()}. La conexión no se guardó.",
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );

        $exception->context = ['provider' => $provider->value, 'error' => $error];

        return $exception;
    }
}
