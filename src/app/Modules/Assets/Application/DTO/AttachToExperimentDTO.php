<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\DTO;

readonly class AttachToExperimentDTO
{
    public function __construct(
        public int $accountId,
        public int $assetId,
        public int $experimentId,
        public ?string $topic = null,
        public int $version = 1,
    ) {}
}
