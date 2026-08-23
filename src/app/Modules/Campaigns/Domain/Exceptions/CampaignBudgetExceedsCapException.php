<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class CampaignBudgetExceedsCapException extends ClientException
{
    public static function overAccountCap(float $requested, float $cap): self
    {
        return self::rejected(
            sprintf(
                'El presupuesto de la campaña (%s) supera el tope por experimento configurado (%s).',
                number_format($requested, 2),
                number_format($cap, 2),
            ),
            ['requested' => $requested, 'cap' => $cap, 'cap_source' => 'budgets.max_per_experiment'],
        );
    }

    public static function overExperimentBudget(float $requested, float $available, int $experimentId): self
    {
        return self::rejected(
            sprintf(
                'El presupuesto de la campaña (%s) supera lo que queda reservado para el experimento (%s).',
                number_format($requested, 2),
                number_format($available, 2),
            ),
            ['requested' => $requested, 'available' => $available, 'experiment_id' => $experimentId],
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function rejected(string $message, array $context): self
    {
        $exception = new self($message, Response::HTTP_UNPROCESSABLE_ENTITY);
        $exception->context = $context;

        return $exception;
    }
}
