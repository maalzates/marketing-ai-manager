<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Application\DTO;

readonly class FetchCommentsDTO
{
    /** @param list<string> $postUrls */
    public function __construct(
        public int $accountId,
        public array $postUrls,
        public int $limitPerPost,
    ) {}
}
