<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Services;

use App\Modules\Admin\Application\DTO\GlobalActionLogFilterDTO;
use App\Modules\Admin\Application\DTO\GlobalUsageFilterDTO;
use App\Modules\Audit\Application\DTO\ActionLogFilterDTO;
use App\Modules\Audit\Application\DTO\UsageFilterDTO;
use App\Modules\Audit\Application\Services\ActionLogService;
use App\Modules\Audit\Application\Services\UsageService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * A thin translation from "the admin asked for everyone" to Audit's own filters. The
 * aggregation itself stays in the Audit module — duplicating it here would give the panel
 * and the user's own usage screen two versions of the same number.
 */
readonly class AdminUsageService
{
    public function __construct(
        private UsageService $usage,
        private ActionLogService $actionLog,
    ) {}

    /** @return Collection<string, Collection<int, array<string, mixed>>> */
    public function summary(GlobalUsageFilterDTO $filters): Collection
    {
        return $this->usage->summary($filters->accountId === null
            ? UsageFilterDTO::acrossAllAccounts($filters->from, $filters->to, $filters->groupBy)
            : UsageFilterDTO::forAccount($filters->accountId, $filters->from, $filters->to, $filters->groupBy));
    }

    /** @return Collection<int, mixed>|LengthAwarePaginator<int, mixed> */
    public function actionLogs(GlobalActionLogFilterDTO $filters): Collection|LengthAwarePaginator
    {
        return $this->actionLog->findAll($filters->accountId === null
            ? ActionLogFilterDTO::acrossAllAccounts(
                $filters->action,
                $filters->origin,
                $filters->from,
                $filters->to,
                $filters->perPage,
                $filters->page,
            )
            : ActionLogFilterDTO::forAccount(
                $filters->accountId,
                $filters->action,
                $filters->origin,
                $filters->from,
                $filters->to,
                $filters->perPage,
                $filters->page,
            ));
    }
}
