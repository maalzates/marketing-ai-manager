<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Repositories;

use App\Modules\Content\Application\DTO\CalendarQueryDTO;
use App\Modules\Content\Application\DTO\CreateScheduleDTO;
use App\Modules\Content\Application\DTO\ScheduleFilterDTO;
use App\Modules\Content\Application\DTO\UpdateScheduleDTO;
use App\Modules\Content\Domain\Contracts\ContentScheduleRepositoryInterface;
use App\Modules\Content\Domain\Enums\ScheduleStatus;
use App\Modules\Content\Domain\Exceptions\ContentPersistenceFailedException;
use App\Modules\Content\Infrastructure\Persistence\ContentSchedule;
use App\Modules\Experiments\Domain\Enums\ExperimentPlatform;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

readonly class ContentScheduleRepository implements ContentScheduleRepositoryInterface
{
    public function __construct(private ContentSchedule $model) {}

    public function findAll(ScheduleFilterDTO $filters): Collection|LengthAwarePaginator
    {
        return $filters->perPage > 0
            ? $this->query($filters)->paginate(perPage: $filters->perPage, page: $filters->page)
            : $this->query($filters)->get();
    }

    public function findById(int $id, int $accountId): ?ContentSchedule
    {
        return $this->model->newQuery()->where('account_id', $accountId)->find($id);
    }

    public function calendar(CalendarQueryDTO $query): Collection
    {
        return $this->model->newQuery()
            ->with('experiment')
            ->where('account_id', $query->accountId)
            ->whereBetween('scheduled_at', [$query->from, $query->to])
            ->when(
                $query->strategyId,
                fn (Builder $builder, int $strategyId) => $builder->whereHas(
                    'experiment',
                    fn (Builder $experiment) => $experiment->where('strategy_id', $strategyId),
                ),
            )
            ->orderBy('scheduled_at')
            ->get();
    }

    public function due(CarbonImmutable $until, int $maxAttempts): Collection
    {
        return $this->model->newQuery()
            ->where('status', ScheduleStatus::Pending)
            ->where('attempts', '<', $maxAttempts)
            ->where('scheduled_at', '<=', $until)
            ->orderBy('scheduled_at')
            ->get();
    }

    public function publishedForExperiment(int $experimentId, int $accountId): ?ContentSchedule
    {
        return $this->model->newQuery()
            ->where('account_id', $accountId)
            ->where('experiment_id', $experimentId)
            ->where('status', ScheduleStatus::Published)
            ->whereNotNull('external_post_id')
            ->latest('published_at')
            ->first();
    }

    public function publishedSince(CarbonImmutable $since): Collection
    {
        return $this->model->newQuery()
            ->where('status', ScheduleStatus::Published)
            ->whereNotNull('external_post_id')
            ->where('published_at', '>=', $since)
            ->get();
    }

    public function accountIdsWithPublishedContent(): Collection
    {
        return $this->model->newQuery()
            ->where('status', ScheduleStatus::Published)
            ->distinct()
            ->pluck('account_id');
    }

    public function create(CreateScheduleDTO $dto, ExperimentPlatform $platform): ContentSchedule
    {
        try {
            return $this->model->newQuery()->create([
                'account_id' => $dto->accountId,
                'experiment_id' => $dto->experimentId,
                'asset_id' => $dto->assetId,
                'platform' => $platform,
                'scheduled_at' => $dto->scheduledAt,
            ]);
        } catch (Throwable $exception) {
            throw ContentPersistenceFailedException::wrap($exception, context: [
                'account_id' => $dto->accountId,
                'experiment_id' => $dto->experimentId,
            ]);
        }
    }

    public function update(ContentSchedule $schedule, UpdateScheduleDTO $dto): ContentSchedule
    {
        try {
            $schedule->update(array_filter([
                'asset_id' => $dto->assetId,
                'scheduled_at' => $dto->scheduledAt,
                'status' => $dto->status,
                'external_post_id' => $dto->externalPostId,
                'published_at' => $dto->status === ScheduleStatus::Published ? CarbonImmutable::now() : null,
            ], fn (mixed $value): bool => $value !== null));

            return $schedule->refresh();
        } catch (Throwable $exception) {
            throw ContentPersistenceFailedException::wrap($exception, context: ['content_schedule_id' => $schedule->id]);
        }
    }

    public function markPublishing(ContentSchedule $schedule): ContentSchedule
    {
        try {
            $schedule->update([
                'status' => ScheduleStatus::Publishing,
                'attempts' => $schedule->attempts + 1,
                'last_error' => null,
            ]);

            return $schedule->refresh();
        } catch (Throwable $exception) {
            throw ContentPersistenceFailedException::wrap($exception, context: ['content_schedule_id' => $schedule->id]);
        }
    }

    public function markPublished(ContentSchedule $schedule, string $externalPostId): ContentSchedule
    {
        try {
            $schedule->update([
                'status' => ScheduleStatus::Published,
                'external_post_id' => $externalPostId,
                'published_at' => CarbonImmutable::now(),
                'last_error' => null,
            ]);

            return $schedule->refresh();
        } catch (Throwable $exception) {
            throw ContentPersistenceFailedException::wrap($exception, context: [
                'content_schedule_id' => $schedule->id,
                'external_post_id' => $externalPostId,
            ]);
        }
    }

    public function markFailed(ContentSchedule $schedule, string $error): ContentSchedule
    {
        try {
            $schedule->update(['status' => ScheduleStatus::Failed, 'last_error' => $error]);

            return $schedule->refresh();
        } catch (Throwable $exception) {
            throw ContentPersistenceFailedException::wrap($exception, context: ['content_schedule_id' => $schedule->id]);
        }
    }

    public function reschedule(ContentSchedule $schedule, CarbonImmutable $scheduledAt, string $reason): ContentSchedule
    {
        try {
            $schedule->update([
                'status' => ScheduleStatus::Pending,
                'scheduled_at' => $scheduledAt,
                'last_error' => $reason,
            ]);

            return $schedule->refresh();
        } catch (Throwable $exception) {
            throw ContentPersistenceFailedException::wrap($exception, context: [
                'content_schedule_id' => $schedule->id,
                'scheduled_at' => $scheduledAt->toIso8601String(),
            ]);
        }
    }

    public function delete(ContentSchedule $schedule): bool
    {
        return (bool) $schedule->delete();
    }

    private function query(ScheduleFilterDTO $filters): Builder
    {
        return $this->model->newQuery()
            ->where('account_id', $filters->accountId)
            ->when($filters->experimentId, fn (Builder $query, int $id) => $query->where('experiment_id', $id))
            ->when($filters->status, fn (Builder $query, ScheduleStatus $status) => $query->where('status', $status))
            ->when($filters->platform, fn (Builder $query, ExperimentPlatform $platform) => $query->where('platform', $platform))
            ->when($filters->from, fn (Builder $query, CarbonImmutable $from) => $query->where('scheduled_at', '>=', $from))
            ->when($filters->to, fn (Builder $query, CarbonImmutable $to) => $query->where('scheduled_at', '<=', $to))
            ->orderBy('scheduled_at');
    }
}
