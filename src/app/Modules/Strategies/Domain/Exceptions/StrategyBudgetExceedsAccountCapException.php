<?php

declare(strict_types=1);

namespace App\Modules\Strategies\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class StrategyBudgetExceedsAccountCapException extends ClientException
{
    public static function forBudget(float $budget, float $cap): self
    {
        $exception = new self(
            "A strategy may not budget more than {$cap} per month.",
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
        $exception->context = ['monthly_budget' => $budget, 'cap' => $cap];

        return $exception;
    }
}
