<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ApiException;

class ReportPersistenceFailedException extends ApiException
{
    // Inherits wrap(): ReportPersistenceFailedException::wrap($throwable, context: [...])
}
