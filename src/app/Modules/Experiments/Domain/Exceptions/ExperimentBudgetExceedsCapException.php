<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class ExperimentBudgetExceedsCapException extends ClientException
{
    public static function overAccountCap(float $requested, float $cap): self
    {
        $exception = new self(
            sprintf(
                'El presupuesto solicitado (%s) supera el tope por experimento de la cuenta (%s).',
                number_format($requested, 2),
                number_format($cap, 2),
            ),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
        $exception->context = ['requested' => $requested, 'cap' => $cap, 'cap_source' => 'budgets.max_per_experiment'];

        return $exception;
    }

    public static function overStrategyBudget(float $requested, float $remaining, int $strategyId): self
    {
        $exception = new self(
            sprintf(
                'El presupuesto solicitado (%s) supera lo que le queda a la estrategia este mes (%s).',
                number_format($requested, 2),
                number_format($remaining, 2),
            ),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
        $exception->context = ['requested' => $requested, 'remaining' => $remaining, 'strategy_id' => $strategyId];

        return $exception;
    }
}
