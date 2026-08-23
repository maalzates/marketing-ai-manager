<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A 404 rather than a 403: an expired, forged or unknown token must be indistinguishable
 * to whoever is probing the public media route.
 */
class InvalidMediaTokenException extends ClientException
{
    public static function rejected(): self
    {
        return new self('Media not found.', Response::HTTP_NOT_FOUND);
    }
}
