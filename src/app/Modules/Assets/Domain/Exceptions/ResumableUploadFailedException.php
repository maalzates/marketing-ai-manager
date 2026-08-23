<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ApiException;

class ResumableUploadFailedException extends ApiException
{
    public static function withStatus(string $stage, int $statusCode, array $context = []): self
    {
        $exception = new self('The upload to Google Drive did not complete.', $statusCode);
        $exception->context = array_merge($context, ['stage' => $stage, 'http_status_code' => $statusCode]);

        return $exception;
    }
}
