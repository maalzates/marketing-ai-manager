<?php

declare(strict_types=1);

namespace App\Modules\Strategies\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ApiException;

class StrategyPersistenceFailedException extends ApiException
{
    // Inherits wrap(): StrategyPersistenceFailedException::wrap($throwable, context: [...])
}
