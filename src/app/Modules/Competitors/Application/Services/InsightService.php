<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Application\Services;

use App\Modules\Competitors\Application\DTO\CreateInsightDTO;
use App\Modules\Competitors\Application\DTO\InsightFilterDTO;
use App\Modules\Competitors\Domain\Contracts\InsightRepositoryInterface;
use App\Modules\Competitors\Domain\Enums\InsightKind;
use App\Modules\Competitors\Domain\Enums\InsightStatus;
use App\Modules\Competitors\Domain\Exceptions\InsightNotFoundException;
use App\Modules\Competitors\Infrastructure\Persistence\Insight;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

readonly class InsightService
{
    public function __construct(private InsightRepositoryInterface $repository) {}

    /**
     * @return Collection<int, Insight>|LengthAwarePaginator<int, Insight>
     */
    public function forAccount(InsightFilterDTO $filters): Collection|LengthAwarePaginator
    {
        return $this->repository->findAll($filters);
    }

    public function find(int $id, int $accountId): Insight
    {
        return $this->repository->findById($id, $accountId) ?? throw InsightNotFoundException::withId($id);
    }

    public function record(CreateInsightDTO $dto): Insight
    {
        return $this->repository->create($dto);
    }

    /**
     * @return Collection<int, string>
     */
    public function titlesOfKind(int $accountId, InsightKind $kind): Collection
    {
        return $this->repository->titlesOfKind($accountId, $kind);
    }

    public function markUsed(int $id, int $accountId): Insight
    {
        return $this->repository->changeStatus($this->find($id, $accountId), InsightStatus::Used);
    }

    public function discard(int $id, int $accountId): Insight
    {
        return $this->repository->changeStatus($this->find($id, $accountId), InsightStatus::Discarded);
    }
}
