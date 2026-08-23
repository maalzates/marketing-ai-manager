<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class CampaignNotOnProviderException extends ClientException
{
    public static function forExperiment(int $experimentId): self
    {
        $exception = new self(
            'La campaña de este experimento todavía no existe en la plataforma de anuncios.',
            Response::HTTP_CONFLICT,
        );

        $exception->context = ['experiment_id' => $experimentId];

        return $exception;
    }
}
