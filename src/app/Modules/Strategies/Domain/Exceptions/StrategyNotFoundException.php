<?php

declare(strict_types=1);

namespace App\Modules\Strategies\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class StrategyNotFoundException extends ClientException
{
    /**
     * A strategy owned by another account is reported as missing, never as forbidden:
     * confirming the id exists would leak the other tenant's data.
     */
    public static function withId(int $id): self
    {
        $exception = new self('Strategy not found.', Response::HTTP_NOT_FOUND);
        $exception->context = ['strategy_id' => $id];

        return $exception;
    }
}
