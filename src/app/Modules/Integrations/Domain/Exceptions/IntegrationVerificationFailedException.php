<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use App\Modules\Integrations\Domain\Enums\VerificationFailure;
use Symfony\Component\HttpFoundation\Response;

/**
 * The provider's own diagnosis stays in the context: a rejected key is echoed back inside
 * some 401 bodies, and the message is the one string the caller gets to read. Which
 * message that is matters — telling somebody to regenerate a working key because the
 * provider was down, or because they lack a role, wastes their afternoon.
 */
class IntegrationVerificationFailedException extends ClientException
{
    public static function forProvider(
        IntegrationProvider $provider,
        VerificationFailure $failure,
        array $diagnosis,
    ): self {
        $exception = new self(self::message($provider, $failure), Response::HTTP_UNPROCESSABLE_ENTITY);

        $exception->context = [
            'provider' => $provider->value,
            'failure' => $failure->value,
            'diagnosis' => $diagnosis,
        ];

        return $exception;
    }

    private static function message(IntegrationProvider $provider, VerificationFailure $failure): string
    {
        return match ($failure) {
            VerificationFailure::CREDENTIAL_REJECTED => "{$provider->label()} rechazó las credenciales. Revísalas y vuelve a intentarlo.",
            VerificationFailure::PERMISSION_DENIED => self::permissionMessage($provider),
            VerificationFailure::PROVIDER_UNAVAILABLE => "{$provider->label()} no respondió a la comprobación. Tus credenciales no se han descartado; inténtalo de nuevo en unos minutos.",
        };
    }

    /**
     * Meta is the one provider where a permission error is almost never about the
     * credential: in development mode the app only answers to people with a role on it.
     */
    private static function permissionMessage(IntegrationProvider $provider): string
    {
        return $provider === IntegrationProvider::META
            ? 'Tus credenciales de Meta son válidas, pero la app no tiene permiso sobre esa cuenta. Añádete como tester o developer de la app en Meta for Developers y comprueba que tu usuario tenga rol en el Business Manager de la cuenta publicitaria.'
            : "Tus credenciales de {$provider->label()} son válidas, pero la cuenta no tiene permiso o saldo para usarlas. Revísalo en el panel del proveedor.";
    }
}
