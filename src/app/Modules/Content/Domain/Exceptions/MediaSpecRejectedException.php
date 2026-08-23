<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Caught before the container is created: an out-of-spec file costs a container creation
 * from the 400/24 h ceiling and only fails minutes later, while polling.
 */
class MediaSpecRejectedException extends ClientException
{
    /** @param  list<string>  $violations */
    public static function withViolations(int $assetId, array $violations): self
    {
        $exception = new self(
            sprintf('The linked file does not meet the channel specification: %s', implode(' ', $violations)),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
        $exception->context = ['asset_id' => $assetId, 'violations' => $violations];

        return $exception;
    }
}
