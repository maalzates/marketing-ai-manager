<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class ScriptRejectedException extends ClientException
{
    public static function withId(int $id): self
    {
        $exception = new self('A rejected script cannot be approved. Duplicate it instead.', Response::HTTP_CONFLICT);
        $exception->context = ['content_script_id' => $id];

        return $exception;
    }
}
