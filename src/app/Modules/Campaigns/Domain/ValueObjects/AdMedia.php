<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Domain\ValueObjects;

use App\Modules\Campaigns\Domain\Enums\AdMediaKind;

/**
 * A piece already uploaded to the platform: the identifier the platform gave it back,
 * cached on the Asset so the same bytes are never uploaded twice.
 */
readonly class AdMedia
{
    public function __construct(
        public AdMediaKind $kind,
        public string $externalId,
        public ?string $thumbnailExternalId = null,
    ) {}
}
