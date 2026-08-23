<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class ProposalNotFoundException extends ClientException
{
    public static function withId(int $id): self
    {
        $exception = new self('Proposal not found.', Response::HTTP_NOT_FOUND);
        $exception->context = ['proposal_id' => $id];

        return $exception;
    }
}
