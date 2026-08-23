<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain\Contracts;

use App\Modules\Audit\Application\DTO\RecordApifyUsageDTO;
use App\Modules\Audit\Application\DTO\UsageFilterDTO;
use App\Modules\Audit\Infrastructure\Persistence\ApifyUsageLog;
use Illuminate\Support\Collection;

interface ApifyUsageLogRepositoryInterface
{
    public function create(RecordApifyUsageDTO $dto): ApifyUsageLog;

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function summary(UsageFilterDTO $filters): Collection;
}
