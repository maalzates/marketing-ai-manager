<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Infrastructure\Persistence;

use App\Modules\Campaigns\Domain\Enums\CampaignObjective;
use App\Modules\Campaigns\Domain\Enums\CampaignStatus;
use App\Modules\Campaigns\Domain\Enums\LearningStage;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory;

    protected $fillable = [
        'account_id',
        'experiment_id',
        'external_campaign_id',
        'external_adset_id',
        'external_ad_id',
        'objective',
        'daily_budget',
        'lifetime_budget',
        'targeting',
        'status',
        'advantage_plus_creative',
        'sandbox',
        'learning_stage',
        'last_synced_at',
    ];

    /** The json column is not nullable, so an insert without targeting still needs a shape. */
    protected $attributes = [
        'targeting' => '{}',
    ];

    public function experiment(): BelongsTo
    {
        return $this->belongsTo(Experiment::class);
    }

    protected static function newFactory(): Factory
    {
        return CampaignFactory::new();
    }

    protected function casts(): array
    {
        return [
            'objective' => CampaignObjective::class,
            'status' => CampaignStatus::class,
            'learning_stage' => LearningStage::class,
            'targeting' => 'array',
            'daily_budget' => 'decimal:2',
            'lifetime_budget' => 'decimal:2',
            'advantage_plus_creative' => 'boolean',
            'sandbox' => 'boolean',
            'last_synced_at' => 'immutable_datetime',
        ];
    }
}
