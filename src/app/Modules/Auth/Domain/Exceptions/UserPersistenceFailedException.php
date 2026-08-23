<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ApiException;

class UserPersistenceFailedException extends ApiException {}
