<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class AdAccountNotConfiguredException extends ClientException
{
    public static function forAccount(int $accountId, bool $sandbox, string $settingKey): self
    {
        $exception = new self(
            $sandbox
                ? 'El modo sandbox está activo pero no hay una cuenta publicitaria sandbox configurada.'
                : 'No hay una cuenta publicitaria de Meta configurada para esta cuenta.',
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );

        $exception->context = ['account_id' => $accountId, 'sandbox' => $sandbox, 'setting' => $settingKey];

        return $exception;
    }
}
