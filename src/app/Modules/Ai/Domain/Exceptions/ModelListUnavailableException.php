<?php

declare(strict_types=1);

namespace App\Modules\Ai\Domain\Exceptions;

use App\Modules\Ai\Domain\Enums\LlmProvider;
use App\Modules\Core\Domain\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * A provider that will not list its models is a refresh that did not happen, not an outage of
 * this application: the last known catalogue keeps serving, so this is a warning.
 */
class ModelListUnavailableException extends ApiException
{
    public static function forProvider(LlmProvider $provider, Throwable $previous): self
    {
        $exception = new self(
            "{$provider->value} did not answer with its model list.",
            Response::HTTP_BAD_GATEWAY,
            $previous,
        );
        $exception->context = ['provider' => $provider->value, 'reason' => $previous->getMessage()];

        return $exception;
    }
}
