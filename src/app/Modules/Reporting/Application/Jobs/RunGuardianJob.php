<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Jobs;

use App\Modules\Reporting\Application\Services\GuardianService;
use App\Modules\Reporting\Application\Services\ReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunGuardianJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $accountId, private readonly int $strategyId) {}

    public function handle(GuardianService $guardian, ReportService $reports): void
    {
        $guardian->runForStrategy($this->strategyId, $this->accountId);

        $reports->generatePeriodic($this->strategyId, $this->accountId);
    }
}
