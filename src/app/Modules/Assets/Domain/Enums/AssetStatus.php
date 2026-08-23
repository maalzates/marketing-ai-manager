<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Enums;

enum AssetStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Used = 'used';
    case Archived = 'archived';
    case Broken = 'broken';

    /** Only a piece that still resolves in Drive may be handed to a publisher. */
    public function isPublishable(): bool
    {
        return in_array($this, [self::Ready, self::Used], true);
    }
}
