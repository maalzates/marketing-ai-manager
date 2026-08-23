<?php

declare(strict_types=1);

namespace App\Modules\Settings\Application\DTO;

readonly class SettingScopeFilterDTO
{
    public function __construct(
        public int $accountId,
        public ?int $strategyId = null,
    ) {}
}
