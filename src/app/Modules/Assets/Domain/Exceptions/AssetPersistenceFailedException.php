<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ApiException;

class AssetPersistenceFailedException extends ApiException
{
    // Inherits wrap(): AssetPersistenceFailedException::wrap($throwable, context: [...])
}
