<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Application\DTO;

use Carbon\CarbonImmutable;

readonly class CompetitorCommentDTO
{
    public function __construct(
        public string $externalId,
        public string $postUrl,
        public ?string $author,
        public string $text,
        public int $likes,
        public ?CarbonImmutable $postedAt,
    ) {}
}
