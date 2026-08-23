<?php

declare(strict_types=1);

namespace App\Modules\Ai\Domain\Exceptions;

use App\Modules\Ai\Domain\Enums\LlmProvider;
use App\Modules\Core\Domain\Exceptions\ApiCallFailedException;
use App\Modules\Core\Domain\Exceptions\ClientException;
use App\Modules\Core\Domain\Support\SecretMasker;
use Symfony\Component\HttpFoundation\Response;

/**
 * 422 rather than 401: the caller's own session is fine, it is the key they stored for the
 * provider that was refused. A 401 here would make the SPA sign them out over someone
 * else's credential.
 */
class LlmCredentialRejectedException extends ClientException
{
    public static function forProvider(LlmProvider $provider, ApiCallFailedException $exception): self
    {
        $rejection = new self(
            sprintf('Your %s API key was rejected. Reconnect it in Settings → Integrations.', $provider->label()),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $exception,
        );

        $rejection->context = (new SecretMasker)->mask([
            'provider' => $provider->value,
            ...$exception->getContext(),
        ]);

        return $rejection;
    }
}
