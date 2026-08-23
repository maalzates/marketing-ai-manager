<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ApiException;

class AccountPersistenceFailedException extends ApiException
{
    // Inherits wrap(): AccountPersistenceFailedException::wrap($throwable, context: [...])
}
