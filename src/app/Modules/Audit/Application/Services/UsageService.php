<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application\Services;

use App\Modules\Audit\Application\DTO\RecordApifyUsageDTO;
use App\Modules\Audit\Application\DTO\RecordLlmUsageDTO;
use App\Modules\Audit\Application\DTO\UsageFilterDTO;
use App\Modules\Audit\Domain\Contracts\ApifyUsageLogRepositoryInterface;
use App\Modules\Audit\Domain\Contracts\LlmUsageLogRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

readonly class UsageService
{
    public function __construct(
        private LlmUsageLogRepositoryInterface $llmLogs,
        private ApifyUsageLogRepositoryInterface $apifyLogs,
    ) {}

    public function recordLlmCall(RecordLlmUsageDTO $dto): void
    {
        $this->llmLogs->create($dto);
    }

    public function recordApifyCall(RecordApifyUsageDTO $dto): void
    {
        $this->apifyLogs->create($dto);
    }

    /**
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    public function summary(UsageFilterDTO $filters): Collection
    {
        return collect([
            'llm' => $this->llmLogs->summary($filters),
            'apify' => $this->apifyLogs->summary($filters),
        ]);
    }

    public function spentToday(int $accountId): int
    {
        return $this->llmLogs->totalTokensBetween(
            $accountId,
            CarbonImmutable::now()->startOfDay(),
            CarbonImmutable::now()->endOfDay(),
        );
    }

    public function spentThisMonth(int $accountId): int
    {
        return $this->llmLogs->totalTokensBetween(
            $accountId,
            CarbonImmutable::now()->startOfMonth(),
            CarbonImmutable::now()->endOfMonth(),
        );
    }
}
