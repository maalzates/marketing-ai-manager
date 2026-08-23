<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Application\DTO;

readonly class AccountFilterDTO
{
    public function __construct(public int $accountId) {}
}
