<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\DTO;

use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Domain\Enums\AssetType;

readonly class PersistAssetDTO
{
    /**
     * @param  list<array{code: string, message: string}>  $specWarnings
     */
    public function __construct(
        public int $accountId,
        public AssetType $type,
        public string $name,
        public ?int $strategyId = null,
        public ?int $experimentId = null,
        public ?int $parentAssetId = null,
        public ?int $position = null,
        public ?string $driveFileId = null,
        public ?string $driveFolderId = null,
        public ?string $aspectRatio = null,
        public ?int $durationSeconds = null,
        public ?int $sizeBytes = null,
        public ?string $mimeType = null,
        public AssetStatus $status = AssetStatus::Draft,
        public array $specWarnings = [],
    ) {}
}
