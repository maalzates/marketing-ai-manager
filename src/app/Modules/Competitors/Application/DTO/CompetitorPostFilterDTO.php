<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Application\DTO;

use App\Modules\Competitors\Domain\Enums\Sentiment;

readonly class CompetitorPostFilterDTO
{
    public function __construct(
        public int $accountId,
        public int $competitorId,
        public ?Sentiment $sentiment = null,
        public int $perPage = 0,
        public int $page = 1,
    ) {}
}
