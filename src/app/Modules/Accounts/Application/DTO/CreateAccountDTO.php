<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Application\DTO;

readonly class CreateAccountDTO
{
    public function __construct(
        public string $name,
        public int $ownerUserId,
        public ?string $currency = null,
        public ?string $timezone = null,
    ) {}
}
