<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\DTO;

use App\Modules\Content\Domain\Enums\ScheduleStatus;
use App\Modules\Experiments\Domain\Enums\ExperimentPlatform;
use Carbon\CarbonImmutable;

readonly class ScheduleFilterDTO
{
    public function __construct(
        public int $accountId,
        public ?int $experimentId,
        public ?ScheduleStatus $status,
        public ?ExperimentPlatform $platform,
        public ?CarbonImmutable $from,
        public ?CarbonImmutable $to,
        public int $perPage,
        public int $page,
    ) {}
}
