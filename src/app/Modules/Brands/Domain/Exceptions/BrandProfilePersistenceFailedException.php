<?php

declare(strict_types=1);

namespace App\Modules\Brands\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ApiException;

class BrandProfilePersistenceFailedException extends ApiException
{
    // Inherits wrap(): BrandProfilePersistenceFailedException::wrap($throwable, context: [...])
}
