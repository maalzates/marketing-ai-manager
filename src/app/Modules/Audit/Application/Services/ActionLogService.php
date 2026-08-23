<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application\Services;

use App\Modules\Audit\Application\DTO\ActionLogFilterDTO;
use App\Modules\Audit\Application\DTO\RecordActionDTO;
use App\Modules\Audit\Domain\Contracts\ActionLogRepositoryInterface;
use App\Modules\Core\Domain\Support\SecretMasker;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

readonly class ActionLogService
{
    public function __construct(
        private ActionLogRepositoryInterface $repository,
        private SecretMasker $masker,
    ) {}

    public function record(RecordActionDTO $dto): void
    {
        $this->repository->create($dto, $this->masker->mask($dto->payload));
    }

    public function findAll(ActionLogFilterDTO $filters): Collection|LengthAwarePaginator
    {
        return $this->repository->findAll($filters);
    }
}
