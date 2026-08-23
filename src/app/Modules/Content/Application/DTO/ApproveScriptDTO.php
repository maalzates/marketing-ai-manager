<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\DTO;

use App\Modules\Experiments\Domain\Enums\ExperimentPlatform;
use Carbon\CarbonImmutable;

readonly class ApproveScriptDTO
{
    public function __construct(
        public int $accountId,
        public int $scriptId,
        public ExperimentPlatform $platform,
        public string $hypothesis,
        public ?array $expectedResult,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public ?int $userId = null,
    ) {}
}
