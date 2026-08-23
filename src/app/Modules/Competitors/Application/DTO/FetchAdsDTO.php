<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Application\DTO;

readonly class FetchAdsDTO
{
    public function __construct(
        public int $accountId,
        public string $handle,
        public int $limit,
        public ?string $onlyNewerThan = null,
    ) {}
}
