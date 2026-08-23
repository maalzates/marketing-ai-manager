<?php

declare(strict_types=1);

namespace App\Modules\Brands\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class BrandProfileInUseException extends ClientException
{
    public static function withId(int $id): self
    {
        $exception = new self(
            'This brand profile still has strategies attached and cannot be deleted.',
            Response::HTTP_CONFLICT,
        );
        $exception->context = ['brand_profile_id' => $id];

        return $exception;
    }
}
