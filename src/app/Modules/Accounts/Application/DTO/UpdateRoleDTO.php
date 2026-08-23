<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Application\DTO;

readonly class UpdateRoleDTO
{
    public function __construct(
        public int $roleId,
        public ?string $name = null,
        public ?string $label = null,
    ) {}
}
