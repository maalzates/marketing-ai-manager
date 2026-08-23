<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain\Contracts;

use App\Modules\Audit\Application\DTO\ActionLogFilterDTO;
use App\Modules\Audit\Application\DTO\RecordActionDTO;
use App\Modules\Audit\Infrastructure\Persistence\ActionLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ActionLogRepositoryInterface
{
    public function findAll(ActionLogFilterDTO $filters): Collection|LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $maskedPayload  what actually gets persisted; the DTO's raw payload never does
     */
    public function create(RecordActionDTO $dto, array $maskedPayload): ActionLog;
}
