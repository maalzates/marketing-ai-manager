<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ApiException;

class ApifyRunFailedException extends ApiException
{
    public static function forRun(string $runId, string $status): self
    {
        $exception = new self("Apify run {$runId} ended as {$status}.");
        $exception->context = ['run_id' => $runId, 'status' => $status];

        return $exception;
    }
}
