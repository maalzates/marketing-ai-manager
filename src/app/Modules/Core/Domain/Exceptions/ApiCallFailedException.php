<?php

declare(strict_types=1);

namespace App\Modules\Core\Domain\Exceptions;

use App\Modules\Core\Domain\Support\SecretMasker;
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
            'options' => self::loggableOptions($options),
            'http_status_code' => $httpStatusCode,
            'response_body' => $responseBody,
        ];

        return $exception;
    }

    /**
     * Guzzle's options array is not safe to log as it comes. It can carry closures, streams
     * and handler objects, which a JSON-encoding log handler either drops or chokes on, and
     * it can carry a per-request credential, which must never reach a log line at all.
     *
     * So: keep only what is genuinely serialisable, and mask anything that looks like a
     * secret on the way through.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private static function loggableOptions(array $options): array
    {
        return (new SecretMasker)->mask(
            collect($options)
                ->map(fn (mixed $value): mixed => match (true) {
                    is_scalar($value), $value === null => $value,
                    is_array($value) => self::loggableOptions($value),
                    default => get_debug_type($value),
                })
                ->all()
        );
    }
}
