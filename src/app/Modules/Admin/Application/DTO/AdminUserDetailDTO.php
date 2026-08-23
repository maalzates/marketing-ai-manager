<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\DTO;

use Carbon\CarbonImmutable;

readonly class AdminUserDetailDTO
{
    public function __construct(
        public int $userId,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public int $actionLogPerPage,
    ) {}
}
