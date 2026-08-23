<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Domain\Enums;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case Launching = 'launching';
    case Paused = 'paused';
    case Active = 'active';
    case Failed = 'failed';
    case Archived = 'archived';

    /** Only a campaign that reached the platform can be paused, synced or re-budgeted there. */
    public function existsOnProvider(): bool
    {
        return in_array($this, [self::Paused, self::Active, self::Archived], true);
    }

    /** Archived and failed campaigns have no new delivery to fetch, so the daily sweep skips them. */
    public function keepsAccruingMetrics(): bool
    {
        return $this === self::Paused || $this === self::Active;
    }
}
