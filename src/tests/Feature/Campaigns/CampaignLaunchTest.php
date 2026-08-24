<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Assets\Domain\Enums\AssetType;
use App\Modules\Assets\Domain\Enums\MetaAssetType;
use App\Modules\Assets\Infrastructure\Persistence\Asset;
use App\Modules\Campaigns\Application\DTO\LaunchCampaignDTO;
use App\Modules\Campaigns\Application\Jobs\LaunchCampaignJob;
use App\Modules\Campaigns\Application\Services\CampaignService;
use App\Modules\Campaigns\Domain\Enums\CampaignObjective;
use App\Modules\Campaigns\Domain\Exceptions\CampaignBudgetExceedsCapException;
use App\Modules\Campaigns\Domain\ValueObjects\BudgetPlan;
use App\Modules\Core\Domain\Exceptions\ApiException;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;
use App\Modules\Proposals\Domain\Enums\ProposalType;
use App\Modules\Proposals\Infrastructure\Persistence\Proposal;
use App\Modules\Settings\Domain\Enums\SettingScope;
use App\Modules\Settings\Infrastructure\Persistence\Setting;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Carbon\CarbonImmutable;
use Database\Seeders\DomainKnowledgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeTransport;
use Tests\TestCase;

/**
 * The money path. Everything asserted here is asserted on the bytes that left for Meta,
 * not on a flag inside the process: a budget the platform reads in the wrong unit and an
 * enhancement Meta applies because we never said no both cost real money, and both look
 * perfectly fine from inside the application.
 */
class CampaignLaunchTest extends TestCase
{
    use RefreshDatabase;

    private const string AD_ACCOUNT = '111111111111111';

    private const string SANDBOX_AD_ACCOUNT = '999999999999999';

    private const string CAMPAIGN_ID = '120210000000000001';

    private const string ADSET_ID = '120210000000000002';

    private const string CREATIVE_ID = '120210000000000003';

    private const string AD_ID = '120210000000000004';

    private const string META_TOKEN = 'EAAsecret-meta-token-that-must-never-be-echoed';

    private Account $account;

    private User $user;

    private Experiment $experiment;

    private Asset $asset;

    private FakeTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-01 09:00:00'));
        $this->seed(DomainKnowledgeSeeder::class);

        $this->transport = FakeTransport::silent()->install($this->app);
        $this->user = User::factory()->create();
        $this->account = Account::factory()->create(['owner_user_id' => $this->user->id, 'currency' => 'USD']);
        $this->user->accounts()->attach($this->account);

        Integration::factory()->meta()->create([
            'account_id' => $this->account->id,
            'credentials' => ['access_token' => self::META_TOKEN, 'token_type' => 'bearer'],
        ]);

        $this->setting('campaigns.meta_ad_account_id', self::AD_ACCOUNT);
        $this->setting('campaigns.meta_sandbox_ad_account_id', self::SANDBOX_AD_ACCOUNT);
        $this->setting('budgets.max_per_experiment', 5000);

        $this->experiment = Experiment::factory()->running()->create([
            'account_id' => $this->account->id,
            'strategy_id' => Strategy::factory()->create(['account_id' => $this->account->id])->id,
            'code' => 'EXP-001',
            'title' => 'Reels de septiembre',
            'starts_at' => CarbonImmutable::parse('2026-09-01'),
            'ends_at' => CarbonImmutable::parse('2026-09-11'),
            'max_budget' => 1000.00,
            // A cheap target keeps the launches here above the learning-phase minimum, so the
            // underfunded warning is exercised by the one test that is about it and nothing else.
            'expected_result' => ['metric' => 'cpa', 'operator' => 'lte', 'value' => 2.0],
        ]);

        $this->asset = $this->readyAsset();

        Sanctum::actingAs($this->user);
    }

    public function test_refuses_to_launch_while_a_referenced_asset_is_not_ready(): void
    {
        $draft = Asset::factory()->draft()->create(['account_id' => $this->account->id]);

        $this->acceptProposal(['asset_ids' => [$this->asset->id, $draft->id]])
            ->assertStatus(422)
            ->assertJsonPath('errors.missing_assets', [[
                'asset_id' => $draft->id,
                'reason' => 'está en estado «draft» y no en «ready»',
                'format' => $draft->type->value,
                'aspect_ratio' => $draft->aspect_ratio,
                'duration_seconds' => $draft->duration_seconds,
            ]]);

        $this->assertSame(0, $this->transport->requestCount());
        $this->assertDatabaseCount('campaigns', 0);
    }

    public function test_names_an_asset_that_does_not_belong_to_the_account(): void
    {
        $foreign = Asset::factory()->ready()->create(['account_id' => Account::factory()->create()->id]);

        $this->acceptProposal(['asset_ids' => [$foreign->id]])
            ->assertStatus(422)
            ->assertJsonPath('errors.missing_assets.0.asset_id', $foreign->id)
            ->assertJsonPath('errors.missing_assets.0.reason', 'no existe en la biblioteca de esta cuenta');

        $this->assertSame(0, $this->transport->requestCount());
    }

    public function test_sends_a_daily_budget_to_meta_in_minor_units(): void
    {
        $this->launch(new BudgetPlan(daily: 25.50));

        $this->assertSame(2550, $this->adSetBody()['daily_budget']);
        $this->assertArrayNotHasKey('lifetime_budget', $this->adSetBody());
    }

    public function test_sends_a_lifetime_budget_to_meta_in_minor_units(): void
    {
        $this->launch(new BudgetPlan(lifetime: 300.00));

        $this->assertSame(30000, $this->adSetBody()['lifetime_budget']);
    }

    /** Meta bills these currencies in whole units, so a second ×100 would send a hundredfold budget. */
    public function test_does_not_scale_a_budget_in_a_zero_decimal_currency(): void
    {
        $this->account->update(['currency' => 'JPY']);

        $this->launch(new BudgetPlan(daily: 90.0));

        $this->assertSame(90, $this->adSetBody()['daily_budget']);
    }

    public function test_stores_the_budget_in_the_accounts_own_currency_not_in_minor_units(): void
    {
        $this->launch(new BudgetPlan(daily: 25.50));

        $this->assertDatabaseHas('campaigns', [
            'experiment_id' => $this->experiment->id,
            'daily_budget' => '25.50',
        ]);
    }

    public function test_opts_out_of_every_advantage_plus_creative_enhancement(): void
    {
        $this->launch();

        $features = $this->creativeBody()['degrees_of_freedom_spec']['creative_features_spec'];

        $this->assertNotEmpty($features);
        $this->assertSame(['OPT_OUT'], array_values(array_unique(array_column($features, 'enroll_status'))));
        $this->assertSame('OPT_OUT', $features['standard_enhancements']['enroll_status']);
    }

    public function test_opts_in_only_when_the_human_asked_for_advantage_plus(): void
    {
        $this->launch(advantagePlus: true);

        $features = $this->creativeBody()['degrees_of_freedom_spec']['creative_features_spec'];

        $this->assertSame(['OPT_IN'], array_values(array_unique(array_column($features, 'enroll_status'))));
    }

    public function test_states_advantage_audience_as_off_in_the_outbound_targeting(): void
    {
        $this->launch();

        $this->assertSame(0, $this->adSetBody()['targeting']['targeting_automation']['advantage_audience']);
    }

    public function test_rejects_a_budget_over_the_account_cap_at_the_http_door(): void
    {
        $this->setting('budgets.max_per_experiment', 100);

        $this->acceptProposal(['daily_budget' => 50.0])
            ->assertStatus(422)
            ->assertJsonPath('errors.message', fn (string $message): bool => str_contains($message, '500.00')
                && str_contains($message, '100.00'));

        $this->assertSame(0, $this->transport->requestCount());
        $this->assertDatabaseCount('campaigns', 0);
    }

    /** The proposal door is one of three; the rule has to hold for a caller that never sees a FormRequest. */
    public function test_rejects_a_budget_over_the_account_cap_outside_http(): void
    {
        $this->setting('budgets.max_per_experiment', 100);

        $this->expectException(CampaignBudgetExceedsCapException::class);

        $this->app->make(CampaignService::class)->launch($this->dto(new BudgetPlan(daily: 50.0)));
    }

    public function test_rejects_a_budget_over_the_experiments_own_maximum(): void
    {
        $this->experiment->update(['max_budget' => 200.00]);

        $this->expectException(CampaignBudgetExceedsCapException::class);

        $this->app->make(CampaignService::class)->launch($this->dto(new BudgetPlan(lifetime: 300.0)));
    }

    public function test_routes_a_sandbox_account_to_the_sandbox_ad_account(): void
    {
        $this->account->update(['sandbox_mode' => true]);

        $this->launch();

        $this->assertStringContainsString('act_'.self::SANDBOX_AD_ACCOUNT.'/', $this->transport->path());
        $this->assertStringNotContainsString(self::AD_ACCOUNT, $this->transport->path());
    }

    public function test_reports_the_sandbox_flag_on_the_campaign_it_created(): void
    {
        $this->account->update(['sandbox_mode' => true]);

        $this->launch();

        $this->getJson("/api/v1/campaigns/{$this->experiment->id}")
            ->assertOk()
            ->assertJsonPath('result.sandbox', true)
            ->assertJsonPath('result.external_ad_id', self::AD_ID);
    }

    public function test_uses_the_production_ad_account_when_sandbox_is_off(): void
    {
        $this->launch();

        $this->assertStringContainsString('act_'.self::AD_ACCOUNT.'/', $this->transport->path());
    }

    public function test_never_puts_the_meta_access_token_in_a_response(): void
    {
        $response = $this->acceptProposal();

        $this->assertStringNotContainsString(self::META_TOKEN, $response->getContent());
    }

    public function test_never_puts_the_meta_access_token_in_a_failure_message(): void
    {
        $this->transport->queue(FakeTransport::json([
            'error' => ['message' => 'Invalid OAuth access token.', 'code' => 190],
        ], 400));

        $failure = $this->failedLaunch();

        $this->assertStringNotContainsString(self::META_TOKEN, $failure->getMessage());
        $this->assertStringNotContainsString(self::META_TOKEN, (string) json_encode($failure->getContext()));
    }

    private function failedLaunch(): ApiException
    {
        try {
            $this->app->make(CampaignService::class)->launch($this->dto());
        } catch (ApiException $exception) {
            return $exception;
        }

        $this->fail('The launch was expected to fail on the provider error.');
    }

    /** The token rides in a header the client owns, never in a payload that could be echoed back. */
    public function test_carries_the_access_token_in_the_authorisation_header_only(): void
    {
        $this->launch();

        $this->assertSame('Bearer '.self::META_TOKEN, $this->transport->header('Authorization'));
        $this->assertStringNotContainsString(self::META_TOKEN, $this->transport->body());
        $this->assertStringNotContainsString(self::META_TOKEN, $this->transport->query());
    }

    /** Under the mathematical minimum the ad set never leaves learning — a loud warning, never a block. */
    public function test_warns_about_a_daily_budget_below_the_learning_minimum(): void
    {
        $this->experiment->update(['expected_result' => ['metric' => 'cpa', 'operator' => 'lte', 'value' => 20.0]]);
        $this->queueProviderCreates();

        $result = $this->app->make(CampaignService::class)->launch($this->dto(new BudgetPlan(daily: 20.0)));

        $this->assertCount(1, $result->warnings);
        $this->assertStringContainsString('fase de', $result->warnings[0]);
        $this->assertDatabaseHas('campaigns', ['experiment_id' => $this->experiment->id]);
    }

    private function launch(?BudgetPlan $budget = null, bool $advantagePlus = false): void
    {
        $this->queueProviderCreates();

        LaunchCampaignJob::dispatch($this->dto($budget, $advantagePlus));
    }

    private function dto(?BudgetPlan $budget = null, bool $advantagePlus = false): LaunchCampaignDTO
    {
        return new LaunchCampaignDTO(
            (int) $this->account->id,
            (int) $this->experiment->id,
            CampaignObjective::Traffic,
            $budget ?? new BudgetPlan(daily: 20.0),
            ['geo_locations' => ['countries' => ['ES']]],
            [(int) $this->asset->id],
            '555000111',
            null,
            'Probamos los reels de septiembre.',
            advantagePlusCreative: $advantagePlus,
            userId: (int) $this->user->id,
        );
    }

    private function acceptProposal(array $payload = []): TestResponse
    {
        $this->queueProviderCreates();

        $proposal = Proposal::factory()->pending()->create([
            'account_id' => $this->account->id,
            'experiment_id' => $this->experiment->id,
            'type' => ProposalType::CreateCampaign,
            'payload' => [
                'objective' => CampaignObjective::Traffic->value,
                'daily_budget' => 20.0,
                'targeting' => ['geo_locations' => ['countries' => ['ES']]],
                'asset_ids' => [(int) $this->asset->id],
                'page_id' => '555000111',
                'message' => 'Probamos los reels de septiembre.',
                ...$payload,
            ],
        ]);

        return $this->postJson("/api/v1/proposals/{$proposal->id}/accept");
    }

    private function queueProviderCreates(): void
    {
        $this->transport->queue(
            FakeTransport::json(['id' => self::CAMPAIGN_ID]),
            FakeTransport::json(['id' => self::ADSET_ID]),
            FakeTransport::json(['id' => self::CREATIVE_ID]),
            FakeTransport::json(['id' => self::AD_ID]),
        );
    }

    private function readyAsset(): Asset
    {
        return Asset::factory()->ready()->create([
            'account_id' => $this->account->id,
            'experiment_id' => null,
            'type' => AssetType::Photo,
            'mime_type' => 'image/jpeg',
            'meta_asset_id' => 'abc123imagehash',
            'meta_asset_type' => MetaAssetType::ImageHash,
        ]);
    }

    /** @return array<string, mixed> */
    private function adSetBody(): array
    {
        return $this->transport->decodedBody(1);
    }

    /** @return array<string, mixed> */
    private function creativeBody(): array
    {
        return $this->transport->decodedBody(2);
    }

    private function setting(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(
            ['scope' => SettingScope::ACCOUNT, 'scope_id' => $this->account->id, 'key' => $key],
            ['value' => $value],
        );
    }
}
