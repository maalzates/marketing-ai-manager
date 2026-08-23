<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Drive path is built from the brand and the strategy, so a piece with neither a strategy
 * nor an experiment has nowhere to land — not even the `_inbox/`, which is per brand.
 */
class AssetPlacementUndeterminedException extends ClientException
{
    public static function forAccount(int $accountId): self
    {
        $exception = new self('An asset needs a strategy or an experiment before it can be stored.', Response::HTTP_UNPROCESSABLE_ENTITY);
        $exception->context = ['account_id' => $accountId];

        return $exception;
    }
}
