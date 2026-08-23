<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Application\DTO;

use Carbon\CarbonImmutable;

readonly class CompetitorPostDTO
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $externalId,
        public string $url,
        public string $type,
        public ?string $caption,
        public ?CarbonImmutable $postedAt,
        // Null, never zero: the profile hides its like count.
        public ?int $likes,
        public int $commentsCount,
        public int $views,
        public array $raw,
    ) {}
}
