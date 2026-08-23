<?php

declare(strict_types=1);

namespace App\Modules\Ai\Domain\Exceptions;

use App\Modules\Ai\Domain\Enums\LlmProvider;
use App\Modules\Core\Domain\Exceptions\ApiCallFailedException;
use App\Modules\Core\Domain\Exceptions\ClientException;
use App\Modules\Core\Domain\Support\SecretMasker;
use Symfony\Component\HttpFoundation\Response;

class LlmCallFailedException extends ClientException
{
    public static function forProvider(ApiCallFailedException $exception, LlmProvider $provider, string $model): self
    {
        $failure = new self(
            sprintf('The %s API rejected the request. Check the key and the model in Settings, then try again.', $provider->label()),
            Response::HTTP_BAD_GATEWAY,
            $exception,
        );

        // The wrapped context carries the outbound request options, which include the
        // account's own API key header — it never reaches a log unmasked.
        $failure->context = (new SecretMasker)->mask([
            'provider' => $provider->value,
            'model' => $model,
            ...$exception->getContext(),
        ]);

        return $failure;
    }
}
