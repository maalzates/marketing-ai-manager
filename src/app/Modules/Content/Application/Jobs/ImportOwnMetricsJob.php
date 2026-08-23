<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Jobs;

use App\Modules\Content\Application\Services\OwnMetricsImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportOwnMetricsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $accountId, private readonly int $scheduleId) {}

    public function handle(OwnMetricsImportService $service): void
    {
        $service->importFor($this->accountId, $this->scheduleId);
    }
}
