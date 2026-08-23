<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Domain\Contracts;

use App\Modules\Competitors\Application\DTO\CompetitorFilterDTO;
use App\Modules\Competitors\Application\DTO\CreateCompetitorDTO;
use App\Modules\Competitors\Domain\Enums\CompetitorPlatform;
use App\Modules\Competitors\Infrastructure\Persistence\Competitor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CompetitorRepositoryInterface
{
    /**
     * @return Collection<int, Competitor>|LengthAwarePaginator<int, Competitor>
     */
    public function findAll(CompetitorFilterDTO $filters): Collection|LengthAwarePaginator;

    public function findById(int $id, int $accountId): ?Competitor;

    public function findByHandle(int $accountId, CompetitorPlatform $platform, string $handle): ?Competitor;

    public function create(CreateCompetitorDTO $dto): Competitor;

    public function delete(Competitor $competitor): bool;

    public function markSynced(Competitor $competitor): Competitor;

    /**
     * @return Collection<int, Competitor>
     */
    public function activeForAccount(int $accountId): Collection;

    /**
     * @return Collection<int, int>
     */
    public function accountIdsWithActiveCompetitors(): Collection;
}
