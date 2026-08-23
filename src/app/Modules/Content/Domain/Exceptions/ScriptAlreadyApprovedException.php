<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class ScriptAlreadyApprovedException extends ClientException
{
    public static function withId(int $id, ?int $experimentId): self
    {
        $exception = new self(
            'This script was already approved and has its own experiment.',
            Response::HTTP_CONFLICT,
        );
        $exception->context = ['content_script_id' => $id, 'experiment_id' => $experimentId];

        return $exception;
    }
}
