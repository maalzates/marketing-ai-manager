<?php

declare(strict_types=1);

namespace App\Modules\Strategies\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class StrategyInUseException extends ClientException
{
    public static function withId(int $id): self
    {
        $exception = new self(
            'This strategy still has experiments recorded under it and cannot be deleted. Archive it instead.',
            Response::HTTP_CONFLICT,
        );
        $exception->context = ['strategy_id' => $id];

        return $exception;
    }
}
