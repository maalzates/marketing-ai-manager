<?php

declare(strict_types=1);

namespace Tests\Feature\Strategies;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Brands\Infrastructure\Persistence\BrandProfile;
use App\Modules\Strategies\Domain\Enums\StrategyStatus;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StrategyCrudTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private BrandProfile $brandProfile;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->account = Account::factory()->create(['owner_user_id' => $user->id]);
        $user->accounts()->attach($this->account);
        $this->brandProfile = BrandProfile::factory()->create(['account_id' => $this->account->id]);

        Sanctum::actingAs($user);
    }

    public function test_creates_an_active_strategy_for_the_current_account(): void
    {
        $this->postJson('/api/v1/strategies', $this->payload())
            ->assertCreated()
            ->assertJsonPath('result.name', 'Captación local')
            ->assertJsonPath('result.status', StrategyStatus::Active->value)
            ->assertJsonPath('result.account_id', $this->account->id);

        $this->assertDatabaseHas('strategies', [
            'account_id' => $this->account->id,
            'brand_profile_id' => $this->brandProfile->id,
            'name' => 'Captación local',
            'north_star_metric' => 'cpl',
            'monthly_budget' => '1200.00',
            'status' => StrategyStatus::Active->value,
        ]);
    }

    public function test_stores_the_guardian_config_and_the_organic_cadence_as_json(): void
    {
        $response = $this->postJson('/api/v1/strategies', $this->payload())->assertCreated();

        $this->assertSame(7, $response->json('result.guardian_config.frequency_days'));
        $this->assertSame([9, 19], $response->json('result.organic_cadence.preferred_hours'));
        $this->assertSame(['sin descuentos'], $response->json('result.constraints'));
    }

    public function test_falls_back_to_the_declared_guardian_defaults_when_none_are_sent(): void
    {
        $response = $this->postJson('/api/v1/strategies', [
            'brand_profile_id' => $this->brandProfile->id,
            'name' => 'Mínima',
            'objective' => 'Probar el default.',
            'north_star_metric' => 'roas',
        ])->assertCreated();

        $this->assertSame(
            ['enabled' => true, 'frequency_days' => 1, 'reports_enabled' => true, 'anomaly_multiplier' => 3],
            $response->json('result.guardian_config'),
        );
        $this->assertNull($response->json('result.monthly_budget'));
    }

    public function test_rejects_a_strategy_without_a_north_star_metric(): void
    {
        $this->postJson('/api/v1/strategies', $this->payload(['north_star_metric' => null]))
            ->assertStatus(422)
            ->assertJsonPath('errors.fields.north_star_metric.0', 'The north star metric field is required.');
    }

    /**
     * The screen used to send whatever the user typed into a free-text field, so a metric the
     * product does not measure was stored and never reported on.
     */
    public function test_rejects_a_north_star_metric_outside_the_declared_list(): void
    {
        $this->postJson('/api/v1/strategies', $this->payload(['north_star_metric' => 'engagement']))
            ->assertStatus(422)
            ->assertJsonPath('errors.fields.north_star_metric.0', 'The selected north star metric is invalid.');

        $this->assertDatabaseCount('strategies', 0);
    }

    /** The field the form never asked for, which is why every creation from the screen answered 422. */
    public function test_rejects_a_strategy_without_a_brand_profile(): void
    {
        $payload = $this->payload();
        unset($payload['brand_profile_id']);

        $this->postJson('/api/v1/strategies', $payload)
            ->assertStatus(422)
            ->assertJsonPath('errors.fields.brand_profile_id.0', 'The brand profile id field is required.');

        $this->assertDatabaseCount('strategies', 0);
    }

    /** An untouched budget field on the screen sends an empty string, not a missing key. */
    public function test_an_empty_budget_field_creates_the_strategy_without_a_budget(): void
    {
        $this->postJson('/api/v1/strategies', $this->payload(['monthly_budget' => '']))
            ->assertCreated()
            ->assertJsonPath('result.monthly_budget', null);

        $this->assertDatabaseHas('strategies', [
            'name' => 'Captación local',
            'monthly_budget' => null,
        ]);
    }

    public function test_rejects_a_negative_monthly_budget(): void
    {
        $this->postJson('/api/v1/strategies', $this->payload(['monthly_budget' => -10]))
            ->assertStatus(422)
            ->assertJsonPath('errors.fields.monthly_budget.0', 'The monthly budget field must be at least 0.');
    }

    public function test_refuses_to_create_a_strategy_on_another_accounts_brand_profile(): void
    {
        $foreign = BrandProfile::factory()->create();

        $this->postJson('/api/v1/strategies', $this->payload(['brand_profile_id' => $foreign->id]))
            ->assertNotFound()
            ->assertJsonPath('errors.message', 'Brand profile not found.');

        $this->assertDatabaseMissing('strategies', ['brand_profile_id' => $foreign->id]);
    }

    public function test_reads_a_strategy_of_the_current_account(): void
    {
        $strategy = $this->ownStrategy(['name' => 'Captación local']);

        $this->getJson("/api/v1/strategies/{$strategy->id}")
            ->assertOk()
            ->assertJsonPath('result.name', 'Captación local');
    }

    public function test_updates_a_strategy_of_the_current_account(): void
    {
        $strategy = $this->ownStrategy(['objective' => 'Objetivo viejo']);

        $this->putJson("/api/v1/strategies/{$strategy->id}", ['objective' => 'Objetivo nuevo'])
            ->assertOk()
            ->assertJsonPath('result.objective', 'Objetivo nuevo');

        $this->assertDatabaseHas('strategies', ['id' => $strategy->id, 'objective' => 'Objetivo nuevo']);
    }

    public function test_deletes_a_strategy_of_the_current_account(): void
    {
        $strategy = $this->ownStrategy();

        $this->deleteJson("/api/v1/strategies/{$strategy->id}")->assertNoContent();

        $this->assertDatabaseMissing('strategies', ['id' => $strategy->id]);
    }

    public function test_reports_a_missing_strategy_as_not_found(): void
    {
        $this->getJson('/api/v1/strategies/9999')
            ->assertNotFound()
            ->assertJsonPath('errors.message', 'Strategy not found.');
    }

    public function test_lists_only_the_strategies_of_the_current_account(): void
    {
        $this->ownStrategy(['name' => 'Propia']);
        Strategy::factory()->create(['name' => 'Ajena']);

        $response = $this->getJson('/api/v1/strategies')->assertOk();

        $this->assertSame(['Propia'], collect($response->json('result'))->pluck('name')->all());
    }

    public function test_several_strategies_coexist_on_one_account_with_independent_budgets(): void
    {
        $this->ownStrategy(['name' => 'Orgánica', 'monthly_budget' => 0]);
        $this->ownStrategy(['name' => 'Ads', 'monthly_budget' => 1500]);

        $response = $this->getJson('/api/v1/strategies')->assertOk();

        $this->assertEqualsCanonicalizing(
            ['Orgánica' => '0.00', 'Ads' => '1500.00'],
            collect($response->json('result'))->pluck('monthly_budget', 'name')->all(),
        );
    }

    public function test_filters_the_listing_by_status(): void
    {
        $this->ownStrategy(['name' => 'Viva']);
        $this->ownStrategy(['name' => 'Pausada', 'status' => StrategyStatus::Paused]);
        $this->ownStrategy(['name' => 'Archivada', 'status' => StrategyStatus::Archived]);

        $response = $this->getJson('/api/v1/strategies?status=paused')->assertOk();

        $this->assertSame(['Pausada'], collect($response->json('result'))->pluck('name')->all());
    }

    public function test_rejects_an_unknown_status_filter(): void
    {
        $this->getJson('/api/v1/strategies?status=zombie')
            ->assertStatus(422)
            ->assertJsonPath('errors.fields.status.0', 'The selected status is invalid.');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'brand_profile_id' => $this->brandProfile->id,
            'name' => 'Captación local',
            'objective' => 'Conseguir 30 leads al mes en el barrio.',
            'north_star_metric' => 'cpl',
            'monthly_budget' => 1200,
            'constraints' => ['sin descuentos'],
            'guardian_config' => [
                'enabled' => true,
                'frequency_days' => 7,
                'reports_enabled' => false,
                'anomaly_multiplier' => 2,
            ],
            'organic_cadence' => ['posts_per_week' => 4, 'preferred_hours' => [9, 19]],
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function ownStrategy(array $attributes = []): Strategy
    {
        return Strategy::factory()->create([
            'account_id' => $this->account->id,
            'brand_profile_id' => $this->brandProfile->id,
            ...$attributes,
        ]);
    }
}
