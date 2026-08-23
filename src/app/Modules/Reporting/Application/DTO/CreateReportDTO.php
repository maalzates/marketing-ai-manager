<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\DTO;

use App\Modules\Reporting\Domain\Enums\ReportType;
use Carbon\CarbonImmutable;

readonly class CreateReportDTO
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public int $accountId,
        public ReportType $type,
        public string $body,
        public array $data,
        public CarbonImmutable $generatedAt,
        public ?int $strategyId = null,
        public ?int $experimentId = null,
        public ?CarbonImmutable $periodStart = null,
        public ?CarbonImmutable $periodEnd = null,
    ) {}
}
