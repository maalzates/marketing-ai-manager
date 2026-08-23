<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ApiException;

class ContentPersistenceFailedException extends ApiException
{
    // Inherits wrap(): ContentPersistenceFailedException::wrap($throwable, context: [...])
}
