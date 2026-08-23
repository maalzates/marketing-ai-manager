<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\DTO;

use App\Modules\Assets\Domain\Enums\AssetType;

readonly class LinkExistingAssetDTO
{
    public function __construct(
        public int $accountId,
        public string $driveFileId,
        public AssetType $type,
        public ?int $strategyId = null,
        public ?int $experimentId = null,
    ) {}
}
