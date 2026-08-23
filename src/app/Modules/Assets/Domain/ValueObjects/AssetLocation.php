<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\ValueObjects;

/**
 * Where a piece belongs in Drive, expressed as plain names. Assets never type-hints another
 * module's model, so the names are read from those modules' Services and travel as strings.
 */
readonly class AssetLocation
{
    public function __construct(
        public string $brandName,
        public ?string $strategyName = null,
        public ?string $experimentCode = null,
        public ?string $experimentFolderName = null,
    ) {}

    /** No experiment yet means the piece lands in the brand's `_inbox/`. */
    public function isInbox(): bool
    {
        return $this->experimentFolderName === null;
    }
}
