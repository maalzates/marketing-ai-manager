<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\ValueObjects;

/** Where a piece belongs, resolved once: the Drive path plus the rows it will be attached to. */
readonly class AssetPlacement
{
    public function __construct(
        public AssetLocation $location,
        public ?int $strategyId,
        public ?int $experimentId,
        public ?string $experimentCode,
    ) {}
}
