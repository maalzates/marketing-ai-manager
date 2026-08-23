<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain\Contracts;

use App\Modules\Reporting\Application\DTO\CreateReportDTO;
use App\Modules\Reporting\Application\DTO\ReportFilterDTO;
use App\Modules\Reporting\Infrastructure\Persistence\Report;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ReportRepositoryInterface
{
    /**
     * @return Collection<int, Report>|LengthAwarePaginator<int, Report>
     */
    public function findAll(ReportFilterDTO $filters): Collection|LengthAwarePaginator;

    public function findById(int $id, int $accountId): ?Report;

    public function findVerdictReport(int $experimentId, int $accountId): ?Report;

    public function findPeriodicReport(
        int $strategyId,
        int $accountId,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
    ): ?Report;

    public function create(CreateReportDTO $dto): Report;
}
