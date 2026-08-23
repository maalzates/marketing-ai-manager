<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\Response;

class ProposalExpiredException extends ClientException
{
    public static function at(int $id, CarbonImmutable $expiredAt): self
    {
        $exception = new self(
            sprintf('Esta propuesta caducó el %s; vuelve a pedirla si sigue teniendo sentido.', $expiredAt->format('d/m/Y H:i')),
            Response::HTTP_CONFLICT,
        );
        $exception->context = ['proposal_id' => $id, 'expires_at' => $expiredAt->toIso8601String()];

        return $exception;
    }
}
