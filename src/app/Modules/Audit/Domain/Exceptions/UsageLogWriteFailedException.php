<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ApiException;

class UsageLogWriteFailedException extends ApiException {}
