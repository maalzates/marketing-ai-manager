<?php

declare(strict_types=1);

namespace App\Modules\Chat\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ApiException;

class ChatMessagePersistenceFailedException extends ApiException {}
