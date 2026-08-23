<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\DTO;

readonly class UserRoleDTO
{
    public function __construct(
        public int $userId,
        public string $role,
        public int $actorUserId,
    ) {}
}
