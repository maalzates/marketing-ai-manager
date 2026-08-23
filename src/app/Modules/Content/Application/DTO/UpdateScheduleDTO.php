<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\DTO;

use App\Modules\Content\Domain\Enums\ScheduleStatus;
use Carbon\CarbonImmutable;

readonly class UpdateScheduleDTO
{
    public function __construct(
        public int $accountId,
        public int $scheduleId,
        public ?int $assetId,
        public ?CarbonImmutable $scheduledAt,
        public ?ScheduleStatus $status,
        public ?string $externalPostId,
    ) {}
}
