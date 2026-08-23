<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ApiException;
use Illuminate\Support\Arr;
use Throwable;

class DriveOperationFailedException extends ApiException
{
    /**
     * ApiCallFailedException carries the whole Guzzle option array, bearer token included.
     * A Drive failure is rebuilt from scratch here so that nothing beyond the operation and
     * Google's own reason code ever reaches the log.
     */
    public static function masked(
        Throwable $previous,
        string $operation,
        int $statusCode,
        ?array $responseBody = null,
        array $context = [],
    ): self {
        $failure = new self('Google Drive rejected the request.', $statusCode, $previous);

        $failure->context = array_merge($context, [
            'operation' => $operation,
            'http_status_code' => $statusCode,
            'drive_code' => Arr::get($responseBody, 'error.code'),
            'drive_reason' => Arr::get($responseBody, 'error.errors.0.reason'),
        ]);

        return $failure;
    }
}
