<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ApiException;

class IntegrationPersistenceFailedException extends ApiException
{
    // Inherits wrap(): IntegrationPersistenceFailedException::wrap($throwable, context: [...])
}
