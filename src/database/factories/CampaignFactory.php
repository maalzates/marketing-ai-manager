<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Campaigns\Domain\Enums\CampaignObjective;
use App\Modules\Campaigns\Domain\Enums\CampaignStatus;
use App\Modules\Campaigns\Domain\Enums\LearningStage;
use App\Modules\Campaigns\Infrastructure\Persistence\Campaign;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'experiment_id' => Experiment::factory(),
            'external_campaign_id' => null,
            'external_adset_id' => null,
            'external_ad_id' => null,
            'objective' => CampaignObjective::Traffic,
            'daily_budget' => 50.00,
            'lifetime_budget' => null,
            'targeting' => [
                'geo_locations' => ['countries' => ['ES']],
                'age_min' => 18,
                'age_max' => 65,
                'publisher_platforms' => ['facebook', 'instagram'],
                'instagram_positions' => ['stream', 'story', 'reels'],
                'targeting_automation' => ['advantage_audience' => 0],
            ],
            'status' => CampaignStatus::Draft,
            'advantage_plus_creative' => false,
            'sandbox' => false,
            'learning_stage' => null,
            'last_synced_at' => null,
        ];
    }

    public function launched(): self
    {
        return $this->state(fn (): array => [
            'external_campaign_id' => (string) fake()->unique()->numerify('1202100000000#####'),
            'external_adset_id' => (string) fake()->unique()->numerify('1202100000000#####'),
            'external_ad_id' => (string) fake()->unique()->numerify('1202100000000#####'),
            'status' => CampaignStatus::Paused,
            'learning_stage' => LearningStage::Learning,
        ]);
    }

    public function sandbox(): self
    {
        return $this->state(fn (): array => ['sandbox' => true]);
    }
}
