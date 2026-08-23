<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Jobs;

use App\Modules\Accounts\Application\Services\AccountService;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Reporting\Application\Services\ReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EvaluateExpiredExperimentsJob implements ShouldQueue
{
    use Queueable;

    public function handle(AccountService $accounts, ReportService $reports): void
    {
        $accounts->findAllActive()->each(
            fn (Account $account) => $reports->generateDueVerdictReports((int) $account->id),
        );
    }
}
