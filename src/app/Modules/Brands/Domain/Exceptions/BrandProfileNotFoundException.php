<?php

declare(strict_types=1);

namespace App\Modules\Brands\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class BrandProfileNotFoundException extends ClientException
{
    /**
     * A profile owned by another account is reported as missing, never as forbidden:
     * confirming the id exists would leak the other tenant's data.
     */
    public static function withId(int $id): self
    {
        $exception = new self('Brand profile not found.', Response::HTTP_NOT_FOUND);
        $exception->context = ['brand_profile_id' => $id];

        return $exception;
    }
}
