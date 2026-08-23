<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Application\DTO;

use App\Modules\Experiments\Domain\Enums\ExperimentPlatform;
use App\Modules\Experiments\Domain\Enums\ExperimentStatus;
use App\Modules\Experiments\Domain\Enums\ExperimentType;
use Carbon\CarbonImmutable;

readonly class CreateExperimentDTO
{
    /**
     * @param  array<string, mixed>|null  $expectedResult
     * @param  array<string, mixed>  $configuration
     */
    public function __construct(
        public int $accountId,
        public int $strategyId,
        public ExperimentType $type,
        public ExperimentPlatform $platform,
        public string $title,
        public string $hypothesis,
        public ?array $expectedResult,
        public CarbonImmutable $startsAt,
        public ?CarbonImmutable $endsAt,
        public ?float $maxBudget,
        public array $configuration,
        public ExperimentStatus $status,
    ) {}
}
