<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Presentation\Http\Resources;

use App\Modules\Campaigns\Infrastructure\Persistence\Campaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Campaign
 *
 * `sandbox` is deliberately not conditional: the permanent badge in the UI reads it from
 * every campaign payload, so a response that omitted it would silently look like production.
 */
class CampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'experiment_id' => $this->experiment_id,
            'objective' => $this->objective->value,
            'status' => $this->status->value,
            'daily_budget' => $this->daily_budget,
            'lifetime_budget' => $this->lifetime_budget,
            'targeting' => $this->targeting,
            'advantage_plus_creative' => $this->advantage_plus_creative,
            'sandbox' => $this->sandbox,
            'learning_stage' => $this->learning_stage?->value,
            'external_campaign_id' => $this->external_campaign_id,
            'external_adset_id' => $this->external_adset_id,
            'external_ad_id' => $this->external_ad_id,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
        ];
    }
}
