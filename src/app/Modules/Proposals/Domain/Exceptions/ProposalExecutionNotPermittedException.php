<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ApiException;

/**
 * Thrown by the container when anything other than the human-approval controller asks for
 * ProposalExecutionService. Seeing this means a Tool, a Job or a Service tried to execute
 * a mutation without a human decision, which is the one thing the architecture forbids.
 */
class ProposalExecutionNotPermittedException extends ApiException
{
    public static function outsideApprovalDoor(): self
    {
        $exception = new self('Proposal execution is reachable only from the human approval endpoint.');
        $exception->context = ['door' => 'POST /api/v1/proposals/{id}/accept'];

        return $exception;
    }
}
