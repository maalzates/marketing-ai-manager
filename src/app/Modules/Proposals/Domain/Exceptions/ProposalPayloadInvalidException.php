<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use App\Modules\Proposals\Domain\Enums\ProposalType;
use Symfony\Component\HttpFoundation\Response;

class ProposalPayloadInvalidException extends ClientException
{
    public static function missing(ProposalType $type, string $field): self
    {
        $exception = new self(
            sprintf('La propuesta de tipo "%s" no trae "%s" y no se puede ejecutar.', $type->value, $field),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
        $exception->context = ['type' => $type->value, 'missing' => $field];

        return $exception;
    }
}
