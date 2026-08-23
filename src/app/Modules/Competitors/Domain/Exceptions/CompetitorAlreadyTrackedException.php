<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Domain\Exceptions;

use App\Modules\Competitors\Domain\Enums\CompetitorPlatform;
use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class CompetitorAlreadyTrackedException extends ClientException
{
    public static function forHandle(CompetitorPlatform $platform, string $handle): self
    {
        $exception = new self(
            "Ya sigues a {$handle} en {$platform->value}.",
            Response::HTTP_CONFLICT,
        );

        $exception->context = ['platform' => $platform->value, 'handle' => $handle];

        return $exception;
    }
}
