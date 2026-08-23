<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\DTO;

readonly class UpdateAdminUserDTO
{
    public function __construct(
        public int $userId,
        public ?string $name,
        public ?bool $isActive,
        public int $actorUserId,
    ) {}
}
