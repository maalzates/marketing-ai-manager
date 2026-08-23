<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class ScheduleAssetMissingException extends ClientException
{
    public static function withId(int $scheduleId): self
    {
        $exception = new self(
            'This slot has no recording linked yet. Link the asset before it can be published.',
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
        $exception->context = ['content_schedule_id' => $scheduleId];

        return $exception;
    }
}
