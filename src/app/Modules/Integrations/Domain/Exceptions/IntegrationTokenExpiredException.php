<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use Symfony\Component\HttpFoundation\Response;

class IntegrationTokenExpiredException extends ClientException
{
    /**
     * Google expires every refresh token after 7 days while the consent screen sits in
     * Testing, so a deployment that never published it loses every Drive connection each
     * week. The hint rides in the context because an operator reading one log line should
     * not have to rediscover that.
     */
    private const string GOOGLE_TESTING_MODE_HINT = 'Google revokes refresh tokens after 7 days while the OAuth consent screen is in Testing. If this is happening across accounts, publish the consent screen in Production before looking anywhere else.';

    public static function forProvider(IntegrationProvider $provider, array $context = []): self
    {
        $exception = new self(
            "Tu conexión con {$provider->label()} caducó. Vuelve a autorizarla desde Integraciones.",
            Response::HTTP_UNAUTHORIZED,
        );

        $exception->context = array_merge(
            ['provider' => $provider->value],
            $provider->usesGoogleOAuth() ? ['hint' => self::GOOGLE_TESTING_MODE_HINT] : [],
            $context,
        );

        return $exception;
    }
}
