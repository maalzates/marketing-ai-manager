<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class OAuthExchangeFailedException extends ClientException
{
    private const string MESSAGE = 'Google no completó el inicio de sesión. Vuelve a intentarlo.';

    /**
     * @param  array<string, mixed>  $diagnosis  Already masked; provider detail never reaches the message.
     */
    public static function withDiagnosis(array $diagnosis): self
    {
        $exception = new self(self::MESSAGE, Response::HTTP_UNPROCESSABLE_ENTITY);
        $exception->context = ['diagnosis' => $diagnosis];

        return $exception;
    }
}
