<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain\Contracts;

use App\Modules\Audit\Application\DTO\RecordLlmUsageDTO;
use App\Modules\Audit\Application\DTO\UsageFilterDTO;
use App\Modules\Audit\Infrastructure\Persistence\LlmUsageLog;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface LlmUsageLogRepositoryInterface
{
    public function create(RecordLlmUsageDTO $dto): LlmUsageLog;

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function summary(UsageFilterDTO $filters): Collection;

    public function totalTokensBetween(int $accountId, CarbonInterface $from, CarbonInterface $to): int;
}
