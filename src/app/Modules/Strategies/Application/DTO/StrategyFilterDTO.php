<?php

declare(strict_types=1);

namespace App\Modules\Strategies\Application\DTO;

use App\Modules\Strategies\Domain\Enums\StrategyStatus;

readonly class StrategyFilterDTO
{
    public function __construct(
        public int $accountId,
        public ?StrategyStatus $status = null,
        public int $perPage = 0,
        public int $page = 1,
    ) {}
}
