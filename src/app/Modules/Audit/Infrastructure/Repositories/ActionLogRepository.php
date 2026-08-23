<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Repositories;

use App\Modules\Audit\Application\DTO\ActionLogFilterDTO;
use App\Modules\Audit\Application\DTO\RecordActionDTO;
use App\Modules\Audit\Domain\Contracts\ActionLogRepositoryInterface;
use App\Modules\Audit\Domain\Enums\ActionOrigin;
use App\Modules\Audit\Domain\Exceptions\ActionLogWriteFailedException;
use App\Modules\Audit\Infrastructure\Persistence\ActionLog;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

readonly class ActionLogRepository implements ActionLogRepositoryInterface
{
    public function __construct(private ActionLog $model) {}

    public function findAll(ActionLogFilterDTO $filters): Collection|LengthAwarePaginator
    {
        return $filters->perPage > 0
            ? $this->query($filters)->paginate(perPage: $filters->perPage, page: $filters->page)
            : $this->query($filters)->get();
    }

    public function create(RecordActionDTO $dto, array $maskedPayload): ActionLog
    {
        try {
            return $this->model->newQuery()->create([
                'account_id' => $dto->accountId,
                'user_id' => $dto->userId,
                'action' => $dto->action,
                'entity_type' => $dto->entityType,
                'entity_id' => $dto->entityId,
                'payload' => $maskedPayload,
                'origin' => $dto->origin,
                'ip' => $dto->ip,
            ]);
        } catch (Throwable $exception) {
            throw ActionLogWriteFailedException::wrap($exception, context: [
                'account_id' => $dto->accountId,
                'action' => $dto->action,
            ]);
        }
    }

    private function query(ActionLogFilterDTO $filters): Builder
    {
        return $this->model->newQuery()
            ->when(
                $filters->accountId !== null,
                fn (Builder $query) => $query->where('account_id', $filters->accountId),
            )
            ->when($filters->action, fn (Builder $query, string $action) => $query->where('action', $action))
            ->when($filters->origin, fn (Builder $query, ActionOrigin $origin) => $query->where('origin', $origin))
            ->when($filters->from, fn (Builder $query, CarbonImmutable $from) => $query->where('created_at', '>=', $from))
            ->when($filters->to, fn (Builder $query, CarbonImmutable $to) => $query->where('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
