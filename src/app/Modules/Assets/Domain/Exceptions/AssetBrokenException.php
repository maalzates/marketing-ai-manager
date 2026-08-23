<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class AssetBrokenException extends ClientException
{
    public static function withId(int $id): self
    {
        $exception = new self('This asset no longer exists in Drive. Re-link it before using it.', Response::HTTP_CONFLICT);
        $exception->context = ['asset_id' => $id];

        return $exception;
    }
}
