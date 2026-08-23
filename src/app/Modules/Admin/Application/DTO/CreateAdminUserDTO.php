<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\DTO;

readonly class CreateAdminUserDTO
{
    /** @param list<string> $roles */
    public function __construct(
        public string $name,
        public string $email,
        public array $roles,
        public int $actorUserId,
    ) {}
}
