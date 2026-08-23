<?php

declare(strict_types=1);

namespace App\Modules\Chat\Application\DTO;

readonly class HistorySearchDTO
{
    public function __construct(
        public int $accountId,
        public string $query,
        public ?int $strategyId,
        public int $limit,
    ) {}
}
