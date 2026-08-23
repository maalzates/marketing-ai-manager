<?php

declare(strict_types=1);

namespace App\Modules\Ai\Domain\Exceptions;

use App\Modules\Ai\Domain\Enums\AiTask;
use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class UnknownAiTaskException extends ClientException
{
    public static function withoutModel(AiTask $task, string $settingKey): self
    {
        $exception = new self(
            sprintf('No model is configured for the "%s" task. Choose one in Settings → Models.', $task->value),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );

        $exception->context = ['task' => $task->value, 'setting_key' => $settingKey];

        return $exception;
    }
}
