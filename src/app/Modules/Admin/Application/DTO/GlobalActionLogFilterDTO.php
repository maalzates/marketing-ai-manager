<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\DTO;

use App\Modules\Audit\Domain\Enums\ActionOrigin;
use Carbon\CarbonImmutable;

readonly class GlobalActionLogFilterDTO
{
    public function __construct(
        public ?int $accountId,
        public ?string $action,
        public ?ActionOrigin $origin,
        public ?CarbonImmutable $from,
        public ?CarbonImmutable $to,
        public int $perPage,
        public int $page,
    ) {}
}
