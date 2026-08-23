<?php

declare(strict_types=1);

namespace App\Modules\Core\Domain\Exceptions;

use App\Modules\Core\Domain\Enums\LogType;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ApiException extends Exception
{
    private const string DEFAULT_MESSAGE = 'Unknown error occurred.';

    /**
     * Codes that describe rejected input rather than a malfunction, so they log at info
     * instead of warning and stay out of the alerting noise.
     *
     * @var list<int>
     */
    private const array INPUT_DRIVEN_STATUS_CODES = [
        Response::HTTP_BAD_REQUEST,
        Response::HTTP_UNPROCESSABLE_ENTITY,
    ];

    protected array $context = [];

    public function __construct(
        ?string $message = null,
        int $code = Response::HTTP_INTERNAL_SERVER_ERROR,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message ?? static::DEFAULT_MESSAGE, $code, $previous);
    }

    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * `code` carries the HTTP status, but a wrapped third-party exception can bring an
     * arbitrary integer with it — anything outside the HTTP range becomes a 500.
     */
    public function getHttpStatusCode(): int
    {
        return $this->getCode() >= Response::HTTP_BAD_REQUEST && $this->getCode() < 600
            ? (int) $this->getCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    public function getLogLevel(): string
    {
        return match (true) {
            in_array($this->getHttpStatusCode(), self::INPUT_DRIVEN_STATUS_CODES, true) => LogType::INFO->value,
            $this->getHttpStatusCode() < Response::HTTP_INTERNAL_SERVER_ERROR => LogType::WARNING->value,
            default => LogType::ERROR->value,
        };
    }

    public static function fromApiCallFailedException(ApiCallFailedException $exception): static
    {
        $domainException = new static($exception->getMessage(), $exception->getHttpStatusCode(), $exception);
        $domainException->context = $exception->getContext();

        return $domainException;
    }

    public static function wrap(
        Throwable $previous,
        ?string $message = null,
        ?int $code = null,
        array $context = [],
    ): static {
        $exception = new static(
            $message ?? $previous->getMessage(),
            $code ?? self::resolveCodeFromPrevious($previous),
            $previous,
        );

        $exception->context = array_merge(self::contextFromPrevious($previous), $context);

        return $exception;
    }

    private static function contextFromPrevious(Throwable $previous): array
    {
        return $previous instanceof self && $previous->getContext() !== []
            ? $previous->getContext()
            : [
                'error' => [
                    'exception' => $previous->getMessage(),
                    'trace' => $previous->getTraceAsString(),
                    'code' => $previous->getCode(),
                ],
            ];
    }

    private static function resolveCodeFromPrevious(Throwable $previous): int
    {
        return $previous->getCode() >= Response::HTTP_BAD_REQUEST && $previous->getCode() < 600
            ? (int) $previous->getCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;
    }
}
