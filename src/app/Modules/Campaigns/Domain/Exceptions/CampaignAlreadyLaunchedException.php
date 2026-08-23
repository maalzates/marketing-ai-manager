<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class CampaignAlreadyLaunchedException extends ClientException
{
    public static function forExperiment(int $experimentId, string $externalCampaignId): self
    {
        $exception = new self(
            'Este experimento ya tiene una campaña creada en la plataforma.',
            Response::HTTP_CONFLICT,
        );

        $exception->context = [
            'experiment_id' => $experimentId,
            'external_campaign_id' => $externalCampaignId,
        ];

        return $exception;
    }
}
