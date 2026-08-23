<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use App\Modules\Proposals\Domain\Enums\ProposalStatus;
use Symfony\Component\HttpFoundation\Response;

/**
 * A 409 rather than a 422: the request was well formed, the proposal simply is not
 * pending any more. A double click must not run the mutation twice.
 */
class ProposalAlreadyDecidedException extends ClientException
{
    public static function withStatus(int $id, ProposalStatus $status): self
    {
        $exception = new self(
            sprintf('Esta propuesta ya está en estado "%s" y no admite otra decisión.', $status->value),
            Response::HTTP_CONFLICT,
        );
        $exception->context = ['proposal_id' => $id, 'status' => $status->value];

        return $exception;
    }
}
