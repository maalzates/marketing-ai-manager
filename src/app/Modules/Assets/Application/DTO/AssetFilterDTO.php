<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\DTO;

use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Domain\Enums\AssetType;

readonly class AssetFilterDTO
{
    public function __construct(
        public int $accountId,
        public ?int $strategyId = null,
        public ?int $experimentId = null,
        public ?AssetType $type = null,
        public ?AssetStatus $status = null,
        public int $perPage = 0,
        public int $page = 1,
    ) {}
}
