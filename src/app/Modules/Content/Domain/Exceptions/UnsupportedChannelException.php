<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use App\Modules\Experiments\Domain\Enums\ExperimentPlatform;
use Symfony\Component\HttpFoundation\Response;

class UnsupportedChannelException extends ClientException
{
    public static function forPlatform(ExperimentPlatform $platform): self
    {
        $exception = new self(
            sprintf('%s has no channel integration in this application yet.', $platform->value),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
        $exception->context = ['platform' => $platform->value];

        return $exception;
    }
}
