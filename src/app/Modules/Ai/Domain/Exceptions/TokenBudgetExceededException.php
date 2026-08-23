<?php

declare(strict_types=1);

namespace App\Modules\Ai\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class TokenBudgetExceededException extends ClientException
{
    public static function forPeriod(string $period, int $limit, int $spent, int $requested): self
    {
        $exception = new self(
            sprintf('The %s token budget of %d tokens has been reached. Raise it in Settings or wait for the next period.', $period, $limit),
            Response::HTTP_TOO_MANY_REQUESTS,
        );

        $exception->context = [
            'period' => $period,
            'limit' => $limit,
            'spent' => $spent,
            'requested' => $requested,
        ];

        return $exception;
    }
}
