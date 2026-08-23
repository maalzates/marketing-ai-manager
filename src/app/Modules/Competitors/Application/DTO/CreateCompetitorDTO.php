<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Application\DTO;

use App\Modules\Competitors\Domain\Enums\CompetitorPlatform;

readonly class CreateCompetitorDTO
{
    public function __construct(
        public int $accountId,
        public CompetitorPlatform $platform,
        public string $handle,
        public ?int $strategyId = null,
        public ?string $displayName = null,
        public ?string $externalId = null,
    ) {}
}
