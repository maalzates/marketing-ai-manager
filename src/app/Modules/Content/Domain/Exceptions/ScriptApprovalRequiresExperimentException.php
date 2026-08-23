<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Approval is not a field. It is what creates the experiment the script will be judged by, so it
 * has one door and an edit cannot walk around it.
 */
class ScriptApprovalRequiresExperimentException extends ClientException
{
    public static function withId(int $id): self
    {
        $exception = new self(
            'Approve the script through its approve endpoint: approval is what creates its experiment.',
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
        $exception->context = ['content_script_id' => $id];

        return $exception;
    }
}
