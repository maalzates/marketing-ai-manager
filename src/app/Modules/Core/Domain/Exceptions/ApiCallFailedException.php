<?php

declare(strict_types=1);

namespace App\Modules\Core\Domain\Exceptions;

use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ApiCallFailedException extends ApiException
{
    private const string MESSAGE = 'API call failed.';

    public function __construct(?string $message = null, int $code = Response::HTTP_INTERNAL_SERVER_ERROR, ?Throwable $previous = null)
    {
        parent::__construct($message ?? self::MESSAGE, $code, $previous);
    }

    public static function forParameters(
        Throwable $throwable,
        string $method,
        string $uri,
        array $options,
        ?int $httpStatusCode = null,
        ?array $responseBody = null,
    ): self {
        $exception = new self(self::MESSAGE, $httpStatusCode ?? Response::HTTP_INTERNAL_SERVER_ERROR, $throwable);

        $exception->context = [
            'error' => [
                'message' => $throwable->getMessage(),
                'code' => $throwable->getCode(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
                'trace' => $throwable->getTraceAsString(),
            ],
            'method' => $method,
            'uri' => $uri,
            'options' => $options,
            'http_status_code' => $httpStatusCode,
            'response_body' => $responseBody,
        ];

        return $exception;
    }
}
