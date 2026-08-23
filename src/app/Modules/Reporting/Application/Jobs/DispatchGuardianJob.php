<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Jobs;

use App\Modules\Accounts\Application\Services\AccountService;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Reporting\Application\Services\GuardianService;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * The daily fan-out. Which strategies are due is GuardianService's decision, not this job's:
 * dispatching one per strategy keeps a slow account from delaying every other account's run.
 */
class DispatchGuardianJob implements ShouldQueue
{
    use Queueable;

    public function handle(AccountService $accounts, GuardianService $guardian): void
    {
        $accounts->findAllActive()->each(
            fn (Account $account) => $guardian->dueStrategies((int) $account->id)->each(
                fn (Strategy $strategy) => RunGuardianJob::dispatch((int) $account->id, (int) $strategy->id),
            ),
        );
    }
}
