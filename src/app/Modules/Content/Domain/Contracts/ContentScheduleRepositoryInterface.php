<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Contracts;

use App\Modules\Content\Application\DTO\CalendarQueryDTO;
use App\Modules\Content\Application\DTO\CreateScheduleDTO;
use App\Modules\Content\Application\DTO\ScheduleFilterDTO;
use App\Modules\Content\Application\DTO\UpdateScheduleDTO;
use App\Modules\Content\Infrastructure\Persistence\ContentSchedule;
use App\Modules\Experiments\Domain\Enums\ExperimentPlatform;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ContentScheduleRepositoryInterface
{
    /** @return Collection<int, ContentSchedule>|LengthAwarePaginator<int, ContentSchedule> */
    public function findAll(ScheduleFilterDTO $filters): Collection|LengthAwarePaginator;

    public function findById(int $id, int $accountId): ?ContentSchedule;

    /** @return Collection<int, ContentSchedule> */
    public function calendar(CalendarQueryDTO $query): Collection;

    /** @return Collection<int, ContentSchedule> */
    public function due(CarbonImmutable $until, int $maxAttempts): Collection;

    public function publishedForExperiment(int $experimentId, int $accountId): ?ContentSchedule;

    /** @return Collection<int, ContentSchedule> */
    public function publishedSince(CarbonImmutable $since): Collection;

    /** @return Collection<int, int> */
    public function accountIdsWithPublishedContent(): Collection;

    public function create(CreateScheduleDTO $dto, ExperimentPlatform $platform): ContentSchedule;

    public function update(ContentSchedule $schedule, UpdateScheduleDTO $dto): ContentSchedule;

    public function markPublishing(ContentSchedule $schedule): ContentSchedule;

    public function markPublished(ContentSchedule $schedule, string $externalPostId): ContentSchedule;

    public function markFailed(ContentSchedule $schedule, string $error): ContentSchedule;

    public function reschedule(ContentSchedule $schedule, CarbonImmutable $scheduledAt, string $reason): ContentSchedule;

    public function delete(ContentSchedule $schedule): bool;
}
