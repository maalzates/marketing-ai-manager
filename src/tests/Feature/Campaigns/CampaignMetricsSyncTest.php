<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Campaigns\Infrastructure\Persistence\Campaign;
use App\Modules\Experiments\Domain\Enums\ExperimentStatus;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;
use App\Modules\Settings\Domain\Enums\SettingScope;
use App\Modules\Settings\Infrastructure\Persistence\Setting;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Carbon\CarbonImmutable;
use Database\Seeders\DomainKnowledgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeTransport;
use Tests\TestCase;

/**
 * The scheduler runs this several times a day and Meta restates yesterday for up to 28
 * hours, so the same day arrives again and again. Every assertion here is about that:
 * a second sync has to correct the series, never grow it, and never inflate the spend the
 * budget guard reads back.
 */
class CampaignMetricsSyncTest extends TestCase
{
    use RefreshDatabase;

    private const string AD_ACCOUNT = '111111111111111';

    private const string CAMPAIGN_ID = '120210000000000001';

    private const string ADSET_ID = '120210000000000002';

    private Account $account;

    private Experiment $experiment;

    private FakeTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-05 10:00:00'));
        $this->seed(DomainKnowledgeSeeder::class);

        $this->transport = FakeTransport::silent()->install($this->app);

        $user = User::factory()->create();
        $this->account = Account::factory()->create(['owner_user_id' => $user->id]);
        $user->accounts()->attach($this->account);

        Integration::factory()->meta()->create(['account_id' => $this->account->id]);
        Setting::query()->create([
            'scope' => SettingScope::ACCOUNT,
            'scope_id' => $this->account->id,
            'key' => 'campaigns.meta_ad_account_id',
            'value' => self::AD_ACCOUNT,
        ]);

        $this->experiment = Experiment::factory()->running()->create([
            'account_id' => $this->account->id,
            'strategy_id' => Strategy::factory()->create(['account_id' => $this->account->id])->id,
            'starts_at' => CarbonImmutable::parse('2026-09-01'),
            'ends_at' => CarbonImmutable::parse('2026-09-30'),
        ]);

        Campaign::factory()->launched()->create([
            'account_id' => $this->account->id,
            'experiment_id' => $this->experiment->id,
            'external_campaign_id' => self::CAMPAIGN_ID,
            'external_adset_id' => self::ADSET_ID,
        ]);

        Sanctum::actingAs($user);
    }

    public function test_writes_one_row_per_day_of_the_series(): void
    {
        $this->queueInsights($this->twoDays());

        $this->postJson("/api/v1/campaigns/{$this->experiment->id}/sync")->assertAccepted();

        $this->assertDatabaseCount('experiment_metrics', 2);
        $this->assertDatabaseHas('experiment_metrics', [
            'experiment_id' => $this->experiment->id,
            'date' => '2026-09-01',
            'spend' => '10.00',
            'impressions' => 1000,
            'clicks' => 40,
        ]);
    }

    public function test_a_second_sync_of_the_same_days_does_not_duplicate_a_row(): void
    {
        $this->queueInsights($this->twoDays());
        $this->postJson("/api/v1/campaigns/{$this->experiment->id}/sync")->assertAccepted();

        $this->queueInsights($this->twoDays());
        $this->postJson("/api/v1/campaigns/{$this->experiment->id}/sync")->assertAccepted();

        $this->assertDatabaseCount('experiment_metrics', 2);
    }

    public function test_a_second_sync_does_not_double_the_experiments_spend_total(): void
    {
        $this->queueInsights($this->twoDays());
        $this->postJson("/api/v1/campaigns/{$this->experiment->id}/sync")->assertAccepted();

        $this->queueInsights($this->twoDays());
        $this->postJson("/api/v1/campaigns/{$this->experiment->id}/sync")->assertAccepted();

        $this->assertSame('35.00', $this->experiment->fresh()->spend_total);
    }

    /** Meta keeps restating a day for over a day, so a re-sync has to overwrite it, not add to it. */
    public function test_a_restated_day_overwrites_the_figures_already_stored(): void
    {
        $this->queueInsights($this->twoDays());
        $this->postJson("/api/v1/campaigns/{$this->experiment->id}/sync")->assertAccepted();

        $this->queueInsights([$this->day('2026-09-01', spend: '18.75', impressions: '1800', clicks: '60')]);
        $this->postJson("/api/v1/campaigns/{$this->experiment->id}/sync")->assertAccepted();

        $this->assertDatabaseHas('experiment_metrics', [
            'experiment_id' => $this->experiment->id,
            'date' => '2026-09-01',
            'spend' => '18.75',
            'impressions' => 1800,
        ]);
        $this->assertDatabaseCount('experiment_metrics', 2);
    }

    public function test_records_the_learning_stage_the_ad_set_reports(): void
    {
        $this->queueInsights($this->twoDays());

        $this->postJson("/api/v1/campaigns/{$this->experiment->id}/sync")->assertAccepted();

        $this->assertDatabaseHas('campaigns', [
            'experiment_id' => $this->experiment->id,
            'learning_stage' => 'SUCCESS',
            'last_synced_at' => '2026-09-05 10:00:00',
        ]);
    }

    public function test_asks_meta_for_one_row_per_day_over_the_experiments_window(): void
    {
        $this->queueInsights($this->twoDays());

        $this->postJson("/api/v1/campaigns/{$this->experiment->id}/sync")->assertAccepted();

        $query = urldecode($this->transport->query());

        $this->assertStringContainsString('time_increment=1', $query);
        $this->assertStringContainsString('"since":"2026-09-01"', $query);
        $this->assertStringContainsString('"until":"2026-09-05"', $query);
    }

    public function test_syncs_nothing_and_calls_nobody_for_an_experiment_that_is_not_running(): void
    {
        $this->experiment->update(['status' => ExperimentStatus::Completed]);

        $this->postJson("/api/v1/campaigns/{$this->experiment->id}/sync")->assertAccepted();

        $this->assertSame(0, $this->transport->requestCount());
        $this->assertDatabaseCount('experiment_metrics', 0);
    }

    public function test_does_not_sync_another_accounts_experiment(): void
    {
        $foreign = Experiment::factory()->running()->create([
            'account_id' => Account::factory()->create()->id,
        ]);

        $this->postJson("/api/v1/campaigns/{$foreign->id}/sync")
            ->assertNotFound()
            ->assertJsonPath('errors.message', 'Experiment not found.');

        $this->assertSame(0, $this->transport->requestCount());
        $this->assertDatabaseCount('experiment_metrics', 0);
    }

    /** @param  list<array<string, mixed>>  $days */
    private function queueInsights(array $days): void
    {
        $this->transport->queue(
            FakeTransport::json(['data' => $days]),
            FakeTransport::json(['id' => self::ADSET_ID, 'learning_stage_info' => ['status' => 'SUCCESS']]),
        );
    }

    /** @return list<array<string, mixed>> */
    private function twoDays(): array
    {
        return [
            $this->day('2026-09-01', spend: '10.00', impressions: '1000', clicks: '40'),
            $this->day('2026-09-02', spend: '25.00', impressions: '2500', clicks: '90'),
        ];
    }

    /** @return array<string, mixed> */
    private function day(string $date, string $spend, string $impressions, string $clicks): array
    {
        return [
            'date_start' => $date,
            'date_stop' => $date,
            'spend' => $spend,
            'impressions' => $impressions,
            'reach' => $impressions,
            'clicks' => $clicks,
            'ctr' => '4.0',
            'cpm' => '10.0',
            'cpc' => '0.25',
            'frequency' => '1.0',
            'actions' => [['action_type' => 'purchase', 'value' => '3']],
        ];
    }
}
