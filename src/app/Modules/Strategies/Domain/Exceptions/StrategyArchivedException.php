<?php

declare(strict_types=1);

namespace App\Modules\Strategies\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class StrategyArchivedException extends ClientException
{
    public static function withId(int $id): self
    {
        $exception = new self(
            'This strategy is archived. Activate it before making any change to it.',
            Response::HTTP_CONFLICT,
        );
        $exception->context = ['strategy_id' => $id];

        return $exception;
    }
}
