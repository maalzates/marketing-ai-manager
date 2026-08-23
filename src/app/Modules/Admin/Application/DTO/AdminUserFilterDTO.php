<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\DTO;

readonly class AdminUserFilterDTO
{
    public function __construct(
        public ?string $search,
        public ?bool $isActive,
        public ?string $role,
        public int $perPage,
        public int $page,
    ) {}
}
