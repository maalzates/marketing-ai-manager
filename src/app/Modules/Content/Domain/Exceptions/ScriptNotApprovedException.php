<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class ScriptNotApprovedException extends ClientException
{
    public static function withId(int $id): self
    {
        $exception = new self(
            'Approve the script first: a recording is linked to the experiment the script created.',
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
        $exception->context = ['content_script_id' => $id];

        return $exception;
    }
}
