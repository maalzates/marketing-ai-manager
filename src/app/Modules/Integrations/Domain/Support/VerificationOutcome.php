<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Domain\Support;

use App\Modules\Integrations\Domain\Enums\VerificationFailure;

/**
 * A rejected credential is an answer, not a malfunction: the caller has to persist the
 * failure before deciding whether to surface it, and only a client may catch. So the
 * verification clients return this instead of throwing.
 */
readonly class VerificationOutcome
{
    private function __construct(
        public bool $valid,
        public ?string $externalAccountId = null,
        public ?VerificationFailure $failure = null,
        public ?int $httpStatus = null,
        public array $diagnosis = [],
    ) {}

    public static function valid(?string $externalAccountId = null): self
    {
        return new self(true, $externalAccountId);
    }

    public static function failed(VerificationFailure $failure, ?int $httpStatus, array $diagnosis): self
    {
        return new self(false, null, $failure, $httpStatus, $diagnosis);
    }
}
