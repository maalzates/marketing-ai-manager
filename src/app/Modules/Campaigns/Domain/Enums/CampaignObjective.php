<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Domain\Enums;

/**
 * The six ODAX outcomes. Meta still lists the fifteen legacy objectives on the reference
 * page, but none of them can create a campaign since Marketing API v17.0 — they exist
 * only on campaigns created before that, so they never enter this app.
 */
enum CampaignObjective: string
{
    case Awareness = 'OUTCOME_AWARENESS';
    case Traffic = 'OUTCOME_TRAFFIC';
    case Engagement = 'OUTCOME_ENGAGEMENT';
    case Leads = 'OUTCOME_LEADS';
    case AppPromotion = 'OUTCOME_APP_PROMOTION';
    case Sales = 'OUTCOME_SALES';

    /** Conversion outcomes are the ones whose ad sets need a promoted object and a conversion domain. */
    public function optimisesForConversions(): bool
    {
        return $this === self::Leads || $this === self::Sales;
    }
}
