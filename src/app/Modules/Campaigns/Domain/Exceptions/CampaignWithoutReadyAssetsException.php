<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Domain\Exceptions;

use App\Modules\Campaigns\Domain\ValueObjects\MissingAsset;
use App\Modules\Core\Domain\Exceptions\ClientException;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

/**
 * The «assets first, campaign second» invariant. The gaps travel in `extras` so the
 * proposal can stay parked as «esperando assets» listing exactly what has to be produced.
 */
class CampaignWithoutReadyAssetsException extends ClientException
{
    /**
     * @param  Collection<int, MissingAsset>  $missing
     */
    public static function missing(int $experimentId, Collection $missing): self
    {
        $exception = new self(
            'La campaña no se puede lanzar: faltan piezas listas en la biblioteca.',
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );

        $exception->extras = ['missing_assets' => $missing->map->toArray()->all()];
        $exception->context = ['experiment_id' => $experimentId] + $exception->extras;

        return $exception;
    }
}
