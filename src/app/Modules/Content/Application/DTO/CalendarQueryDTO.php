<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\DTO;

use Carbon\CarbonImmutable;

readonly class CalendarQueryDTO
{
    public function __construct(
        public int $accountId,
        public ?int $strategyId,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}
}
