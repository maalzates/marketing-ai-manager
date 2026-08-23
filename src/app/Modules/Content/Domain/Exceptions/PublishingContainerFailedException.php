<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Exceptions;

use App\Modules\Content\Domain\Enums\ContainerStatus;
use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A container that came back ERROR, EXPIRED or already PUBLISHED. None of the three can be
 * fixed by trying again with the same container, so this failure is definitive: the piece
 * goes to `failed` and the user is reminded to publish it by hand.
 */
class PublishingContainerFailedException extends ClientException
{
    public static function withStatus(string $containerId, ContainerStatus $status, ?string $detail): self
    {
        $exception = new self(
            sprintf('Instagram could not process the media (%s). Publish this piece manually.', $status->value),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
        $exception->context = [
            'container_id' => $containerId,
            'status_code' => $status->value,
            'status' => $detail,
        ];

        return $exception;
    }
}
