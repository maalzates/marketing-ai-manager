<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Domain\ValueObjects;

/**
 * What an executor actually achieved, as opposed to what it was asked to do.
 *
 * Some mutations finish inside the accept request; others can only be validated there and
 * must run on the queue. The distinction has to reach the proposal, because `executed` is
 * read by a human as "this happened on the platform" — reporting it for work that is merely
 * queued is the same lie as leaving a failed execution on `accepted`.
 */
readonly class ExecutionOutcome
{
    /**
     * @param  array<string, mixed>  $result
     */
    private function __construct(public bool $isDeferred, public array $result) {}

    /**
     * @param  array<string, mixed>  $result
     */
    public static function completed(array $result): self
    {
        return new self(false, $result);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public static function deferred(array $result): self
    {
        return new self(true, $result);
    }
}
