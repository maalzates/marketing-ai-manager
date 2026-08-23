<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\DTO;

use App\Modules\Assets\Domain\Enums\AssetType;

readonly class UploadAssetDTO
{
    public function __construct(
        public int $accountId,
        public AssetType $type,
        public UploadedSourceDTO $source,
        public ?int $strategyId = null,
        public ?int $experimentId = null,
        public ?string $topic = null,
        public int $version = 1,
    ) {}
}
