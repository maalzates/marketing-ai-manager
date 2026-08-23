<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\DTO;

use App\Modules\Reporting\Domain\Enums\ReportType;

readonly class ReportFilterDTO
{
    public function __construct(
        public int $accountId,
        public ?int $strategyId = null,
        public ?int $experimentId = null,
        public ?ReportType $type = null,
        public int $perPage = 0,
        public int $page = 1,
    ) {}
}
