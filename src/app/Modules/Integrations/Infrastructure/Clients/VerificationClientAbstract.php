<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Infrastructure\Clients;

use App\Modules\Core\Domain\Exceptions\ApiCallFailedException;
use App\Modules\Core\Infrastructure\Clients\ApiClientAbstract;
use App\Modules\Integrations\Domain\Enums\VerificationFailure;
use App\Modules\Integrations\Domain\Support\VerificationOutcome;

abstract class VerificationClientAbstract extends ApiClientAbstract
{
    abstract public function verify(): VerificationOutcome;

    /** Reads the provider's own error body — never the HTTP status — to name the failure. */
    abstract protected function classify(array $body, int $httpStatus): VerificationFailure;

    /**
     * Only the status and the provider's own body survive: the rest of the failure context
     * is a stack trace, and this diagnosis is persisted on the integration row.
     */
    protected function failed(ApiCallFailedException $exception): VerificationOutcome
    {
        return VerificationOutcome::failed(
            $this->classify((array) ($exception->getContext()['response_body'] ?? []), $exception->getHttpStatusCode()),
            $exception->getHttpStatusCode(),
            ['body' => $exception->getContext()['response_body']],
        );
    }
}
