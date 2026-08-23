<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use Symfony\Component\HttpFoundation\Response;

class UnsupportedIntegrationProviderException extends ClientException
{
    public static function forApiKey(IntegrationProvider $provider): self
    {
        return self::build("{$provider->label()} no se conecta con una API key.", $provider);
    }

    public static function forOAuth(IntegrationProvider $provider): self
    {
        return self::build("{$provider->label()} no se conecta con OAuth.", $provider);
    }

    public static function notImplemented(IntegrationProvider $provider): self
    {
        return self::build("La integración con {$provider->label()} aún no está disponible.", $provider);
    }

    private static function build(string $message, IntegrationProvider $provider): self
    {
        $exception = new self($message, Response::HTTP_UNPROCESSABLE_ENTITY);
        $exception->context = ['provider' => $provider->value];

        return $exception;
    }
}
