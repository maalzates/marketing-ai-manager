<?php

declare(strict_types=1);

namespace App\Modules\Core\Domain\Exceptions;

use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * An ApiException whose message is safe to show to the end user. Everything else renders
 * as a generic error, so anything the caller is allowed to read must extend this.
 */
class ClientException extends ApiException
{
    private const string DEFAULT_MESSAGE = 'Unknown client exception occurred.';

    protected ?string $clientMessage = null;

    protected array $extras = [];

    public function __construct(
        ?string $message = null,
        int $code = Response::HTTP_BAD_REQUEST,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message ?? self::DEFAULT_MESSAGE, $code, $previous);
    }

    public function getClientMessage(): string
    {
        return $this->clientMessage ?? $this->getMessage();
    }

    public function getExtras(): array
    {
        return $this->extras;
    }
}
