<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class MetaOAuthFailedException extends ClientException
{
    private const string MESSAGE = 'Meta no completó la autorización. Vuelve a intentarlo.';

    public static function withDiagnosis(array $diagnosis): self
    {
        $exception = new self(self::MESSAGE, Response::HTTP_UNPROCESSABLE_ENTITY);
        $exception->context = ['diagnosis' => $diagnosis];

        return $exception;
    }
}
