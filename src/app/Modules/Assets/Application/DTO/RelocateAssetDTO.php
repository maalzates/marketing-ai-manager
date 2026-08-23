<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\DTO;

readonly class RelocateAssetDTO
{
    public function __construct(
        public int $experimentId,
        public ?int $strategyId,
        public string $driveFolderId,
        public string $name,
    ) {}
}
