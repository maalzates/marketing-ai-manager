<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\DTO;

readonly class ApiKeyFilterDTO
{
    public function __construct(
        public ?int $accountId,
        public bool $includeRevoked,
        public int $perPage,
        public int $page,
    ) {}
}
