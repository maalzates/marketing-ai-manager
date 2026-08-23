<?php

declare(strict_types=1);

namespace App\Modules\Ai\Domain\Exceptions;

use App\Modules\Ai\Domain\Enums\LlmProvider;
use App\Modules\Core\Domain\Exceptions\ApiException;

/**
 * Thrown instead of approximating. A turn flattened into prose because the adapter had no
 * faithful mapping produces a confident answer reasoned over garbage, which no test
 * catches and which reaches the user as advice.
 */
class UntranslatableMessageException extends ApiException
{
    public static function forProvider(LlmProvider $provider, string $messageClass): self
    {
        $exception = new self(sprintf(
            'The %s adapter has no faithful translation for a %s turn.',
            $provider->label(),
            class_basename($messageClass),
        ));

        $exception->context = ['provider' => $provider->value, 'message_class' => $messageClass];

        return $exception;
    }
}
