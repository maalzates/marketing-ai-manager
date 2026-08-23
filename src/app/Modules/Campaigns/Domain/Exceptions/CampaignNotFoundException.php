<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class CampaignNotFoundException extends ClientException
{
    public static function forExperiment(int $experimentId): self
    {
        $exception = new self(
            'Este experimento todavía no tiene una campaña creada.',
            Response::HTTP_NOT_FOUND,
        );

        $exception->context = ['experiment_id' => $experimentId];

        return $exception;
    }
}
