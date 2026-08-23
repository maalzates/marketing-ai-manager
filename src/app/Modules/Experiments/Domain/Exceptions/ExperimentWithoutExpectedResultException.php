<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class ExperimentWithoutExpectedResultException extends ClientException
{
    private const string MESSAGE = 'Un experimento necesita un resultado esperado '
        .'({metric, operator, value}) y una fecha de fin. Sin eso no es un experimento, '
        .'es una campaña a ciegas.';

    public static function forStrategy(int $strategyId, array $missing): self
    {
        $exception = new self(self::MESSAGE, Response::HTTP_UNPROCESSABLE_ENTITY);
        $exception->context = ['strategy_id' => $strategyId, 'missing' => $missing];

        return $exception;
    }
}
