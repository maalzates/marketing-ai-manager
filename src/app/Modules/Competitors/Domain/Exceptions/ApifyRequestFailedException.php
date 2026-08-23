<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ApiException;
use Throwable;

/**
 * Anything Apify rejected that the account owner cannot fix: a bad actor input, a missing
 * actor, a transient platform error. The provider's own message never reaches the caller,
 * and the status stays 500 because their 400 is our bug, not the user's.
 */
class ApifyRequestFailedException extends ApiException
{
    /** @param array<string, mixed> $context */
    public static function withContext(array $context, Throwable $previous): self
    {
        $exception = new self('Apify call failed.', previous: $previous);
        $exception->context = $context;

        return $exception;
    }
}
