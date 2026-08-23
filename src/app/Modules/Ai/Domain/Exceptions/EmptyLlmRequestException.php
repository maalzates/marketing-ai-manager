<?php

declare(strict_types=1);

namespace App\Modules\Ai\Domain\Exceptions;

use App\Modules\Ai\Domain\Enums\AiTask;
use App\Modules\Core\Domain\Exceptions\ApiException;

class EmptyLlmRequestException extends ApiException
{
    public static function forTask(AiTask $task): self
    {
        $exception = new self('An AI request was built with no messages to send.');
        $exception->context = ['task' => $task->value];

        return $exception;
    }
}
