<?php

declare(strict_types=1);

namespace App\Modules\Strategies\Domain\Contracts;

use App\Modules\Strategies\Application\DTO\CreateStrategyDTO;
use App\Modules\Strategies\Application\DTO\StrategyFilterDTO;
use App\Modules\Strategies\Application\DTO\UpdateStrategyDTO;
use App\Modules\Strategies\Domain\Enums\StrategyStatus;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface StrategyRepositoryInterface
{
    /**
     * @return Collection<int, Strategy>|LengthAwarePaginator<int, Strategy>
     */
    public function findAll(StrategyFilterDTO $filters): Collection|LengthAwarePaginator;

    public function findById(int $id, int $accountId): ?Strategy;

    public function create(CreateStrategyDTO $dto): Strategy;

    public function update(Strategy $strategy, UpdateStrategyDTO $dto): Strategy;

    public function changeStatus(Strategy $strategy, StrategyStatus $status): Strategy;

    public function delete(Strategy $strategy): bool;

    public function hasStrategiesForBrandProfile(int $brandProfileId, int $accountId): bool;
}
