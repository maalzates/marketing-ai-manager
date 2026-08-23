<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Domain\ValueObjects;

use App\Modules\Campaigns\Infrastructure\Persistence\Campaign;
use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
readonly class CampaignLaunchResult implements Arrayable
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(public Campaign $campaign, public array $warnings = []) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'campaign' => $this->campaign->toArray(),
            'sandbox' => $this->campaign->sandbox,
            'warnings' => $this->warnings,
        ];
    }
}
