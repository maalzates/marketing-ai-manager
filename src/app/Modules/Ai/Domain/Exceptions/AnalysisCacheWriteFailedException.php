<?php

declare(strict_types=1);

namespace App\Modules\Ai\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ApiException;

class AnalysisCacheWriteFailedException extends ApiException
{
    // Inherits wrap(): AnalysisCacheWriteFailedException::wrap($throwable, context: [...])
}
