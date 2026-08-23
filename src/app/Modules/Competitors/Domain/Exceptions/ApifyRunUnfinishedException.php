<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ApiException;

/**
 * The run never reached a terminal status inside the polling budget. It keeps running on
 * Apify and keeps costing the account, so the run id belongs in the context.
 */
class ApifyRunUnfinishedException extends ApiException
{
    public static function forRun(string $runId, string $status): self
    {
        $exception = new self("Apify run {$runId} did not finish in time.");
        $exception->context = ['run_id' => $runId, 'status' => $status];

        return $exception;
    }
}
