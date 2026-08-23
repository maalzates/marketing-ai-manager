<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\DTO;

use App\Modules\Experiments\Domain\Enums\ExperimentPlatform;
use Carbon\CarbonImmutable;

readonly class AudienceSnapshotDTO
{
    public function __construct(
        public int $accountId,
        public ExperimentPlatform $platform,
        public CarbonImmutable $date,
        public int $followersCount,
        public int $followsCount,
        public int $mediaCount,
        public array $raw = [],
    ) {}
}
