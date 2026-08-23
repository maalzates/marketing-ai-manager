<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use Symfony\Component\HttpFoundation\Response;

class ReportNotFoundException extends ClientException
{
    public static function withId(int $id): self
    {
        $exception = new self('Report not found.', Response::HTTP_NOT_FOUND);
        $exception->context = ['report_id' => $id];

        return $exception;
    }
}
