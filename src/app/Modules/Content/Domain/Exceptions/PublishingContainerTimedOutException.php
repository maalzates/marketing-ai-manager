<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Still IN_PROGRESS after the documented five minutes. The container stays valid for 24 h,
 * so this is transient and the job is allowed to try again.
 */
class PublishingContainerTimedOutException extends ClientException
{
    public static function withContainer(string $containerId, int $attempts): self
    {
        $exception = new self('Instagram is still processing the media. It will be retried.', Response::HTTP_ACCEPTED);
        $exception->context = ['container_id' => $containerId, 'attempts' => $attempts];

        return $exception;
    }
}
