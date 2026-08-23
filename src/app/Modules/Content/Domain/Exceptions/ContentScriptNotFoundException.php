<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class ContentScriptNotFoundException extends ClientException
{
    public static function withId(int $id): self
    {
        $exception = new self('Content script not found.', Response::HTTP_NOT_FOUND);
        $exception->context = ['content_script_id' => $id];

        return $exception;
    }
}
