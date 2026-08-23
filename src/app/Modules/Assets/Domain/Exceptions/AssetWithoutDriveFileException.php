<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class AssetWithoutDriveFileException extends ClientException
{
    public static function withId(int $id): self
    {
        $exception = new self('This asset has no file in Drive yet.', Response::HTTP_UNPROCESSABLE_ENTITY);
        $exception->context = ['asset_id' => $id];

        return $exception;
    }
}
