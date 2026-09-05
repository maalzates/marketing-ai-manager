<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Application\DTO;

readonly class UpdateAccountDTO
{
    public function __construct(
        public int $accountId,
        public string $currency,
    ) {}
}
