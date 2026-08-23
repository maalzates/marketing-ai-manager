<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\DTO;

use Carbon\CarbonImmutable;

readonly class ChannelCommentDTO
{
    public function __construct(
        public string $externalId,
        public ?string $author,
        public string $text,
        public int $likes,
        public ?CarbonImmutable $postedAt,
    ) {}
}
