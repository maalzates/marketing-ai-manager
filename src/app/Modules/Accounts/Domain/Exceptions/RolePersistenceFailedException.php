<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ApiException;

class RolePersistenceFailedException extends ApiException
{
    // Inherits wrap(): RolePersistenceFailedException::wrap($throwable, context: [...])
}
