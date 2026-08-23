<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

class ApifyRateLimitedException extends ApiException
{
    /** @param array<string, mixed> $context */
    public static function withContext(array $context): self
    {
        $exception = new self('Apify rate limit exceeded.', Response::HTTP_TOO_MANY_REQUESTS);
        $exception->context = $context;

        return $exception;
    }
}
