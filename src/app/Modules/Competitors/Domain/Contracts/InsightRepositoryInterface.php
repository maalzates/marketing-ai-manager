<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Domain\Contracts;

use App\Modules\Competitors\Application\DTO\CreateInsightDTO;
use App\Modules\Competitors\Application\DTO\InsightFilterDTO;
use App\Modules\Competitors\Domain\Enums\InsightKind;
use App\Modules\Competitors\Domain\Enums\InsightStatus;
use App\Modules\Competitors\Infrastructure\Persistence\Insight;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface InsightRepositoryInterface
{
    /**
     * @return Collection<int, Insight>|LengthAwarePaginator<int, Insight>
     */
    public function findAll(InsightFilterDTO $filters): Collection|LengthAwarePaginator;

    public function findById(int $id, int $accountId): ?Insight;

    public function create(CreateInsightDTO $dto): Insight;

    public function changeStatus(Insight $insight, InsightStatus $status): Insight;

    /**
     * Titles of what has already been captured, for the novelty filter.
     *
     * @return Collection<int, string>
     */
    public function titlesOfKind(int $accountId, InsightKind $kind): Collection;
}
