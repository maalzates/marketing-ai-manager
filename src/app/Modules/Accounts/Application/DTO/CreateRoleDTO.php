<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Application\DTO;

readonly class CreateRoleDTO
{
    public function __construct(public string $name, public string $label) {}
}
