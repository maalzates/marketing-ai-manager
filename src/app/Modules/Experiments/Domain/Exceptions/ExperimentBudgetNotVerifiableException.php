<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The budget check could not be carried out, which is not the same as passing it. Budget
 * limits are validated in the backend (core.md §10.1), so an unanswerable question is a
 * refusal — never a silent zero that lets every later check through.
 */
class ExperimentBudgetNotVerifiableException extends ClientException
{
    public static function uncappedUnderBudgetedStrategy(int $strategyId): self
    {
        $exception = new self(
            'Esta estrategia tiene un presupuesto mensual, así que un experimento de ads necesita su '
            .'propio presupuesto máximo. Sin él no hay forma de comprobar que el mes cabe en el tope.',
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
        $exception->context = ['strategy_id' => $strategyId];

        return $exception;
    }

    /**
     * @param  list<string>  $codes
     */
    public static function strategyHasUncappedExperiments(int $strategyId, array $codes): self
    {
        $exception = new self(
            sprintf(
                'No se puede calcular lo que le queda a la estrategia este mes: %s no tienen presupuesto '
                .'máximo. Ponles uno o ciérralos antes de crear otro experimento.',
                implode(', ', $codes),
            ),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
        $exception->context = ['strategy_id' => $strategyId, 'uncapped' => $codes];

        return $exception;
    }
}
