<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use App\Modules\Proposals\Domain\Enums\ProposalType;
use Symfony\Component\HttpFoundation\Response;

class ProposalExecutorNotAvailableException extends ClientException
{
    public static function forType(ProposalType $type): self
    {
        $exception = new self(
            sprintf('Todavía no se puede ejecutar una propuesta de tipo "%s" desde la aplicación.', $type->value),
            Response::HTTP_NOT_IMPLEMENTED,
        );
        $exception->context = ['type' => $type->value];

        return $exception;
    }
}
