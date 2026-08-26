<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Settings\Application\Services\SettingsService;
use App\Modules\Settings\Domain\Enums\SettingScope;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SettingsWriteTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create();
        Sanctum::actingAs($this->memberOf($this->account));
    }

    public function test_writing_a_declared_key_stores_it_at_account_scope(): void
    {
        $this->putJson('/api/v1/settings', ['values' => ['features.tiktok' => true]])->assertOk();

        $this->assertDatabaseHas('settings', [
            'scope' => SettingScope::ACCOUNT->value,
            'scope_id' => $this->account->id,
            'key' => 'features.tiktok',
        ]);
    }

    public function test_the_response_reports_the_new_value_and_its_scope(): void
    {
        $result = $this->putJson('/api/v1/settings', ['values' => ['features.tiktok' => true]])
            ->assertOk()
            ->json('result');

        $this->assertTrue($result['features.tiktok']['value']);
        $this->assertSame('account', $result['features.tiktok']['scope']);
    }

    /**
     * The contract the Settings screen depends on. Every one of these keys is a field on that
     * page, and each was previously sent under an invented flat name the registry never
     * declared — so the whole screen answered 422 and nothing was ever stored.
     */
    public function test_every_key_the_settings_screen_writes_is_accepted(): void
    {
        $values = [
            'ai.models.same_for_all' => true,
            'ai.models.per_task.chat' => 'gpt-5.6-sol',
            'ai.budget.daily_tokens' => 500000,
            'ai.budget.monthly_tokens' => 9000000,
            'ai.budget.alert_threshold_percent' => 90,
            'apify.budget.daily_calls' => 25,
            'guardian.enabled' => false,
            'guardian.frequency_days' => 3,
            'guardian.reports_enabled' => false,
            'guardian.auto_skip_without_active_experiments' => false,
            'notifications.proposals' => false,
            'notifications.reports' => false,
            'notifications.token_expiry' => false,
            'notifications.usage_limits' => false,
            'campaigns.meta_ad_account_id' => '155760362203',
            'campaigns.meta_sandbox_ad_account_id' => '155760362204',
            'preferences.timezone' => 'America/Bogota',
            'preferences.currency' => 'COP',
            'preferences.locale' => 'en',
        ];

        $result = $this->putJson('/api/v1/settings', ['values' => $values])->assertOk()->json('result');

        foreach ($values as $key => $value) {
            $this->assertSame($value, $result[$key]['value'], $key);
            $this->assertSame('account', $result[$key]['scope'], $key);
        }
    }

    public function test_rejects_a_key_that_is_not_declared_in_the_registry(): void
    {
        $this->putJson('/api/v1/settings', ['values' => ['features.telepathy' => true]])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath(
                'errors.message',
                'The setting "features.telepathy" is not part of the settings registry.',
            );

        $this->assertDatabaseMissing('settings', ['key' => 'features.telepathy']);
    }

    public function test_rejects_a_value_whose_type_does_not_match_the_declared_default(): void
    {
        $this->putJson('/api/v1/settings', ['values' => ['features.tiktok' => 'yes']])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.message', 'The setting "features.tiktok" expects a value of type bool, string given.');

        $this->assertDatabaseMissing('settings', ['key' => 'features.tiktok']);
    }

    /**
     * A float-declared key arrives from the client as a JSON integer whenever the value is
     * whole, so the type check has to coerce rather than refuse.
     */
    public function test_accepts_a_whole_number_for_a_key_declared_as_a_float(): void
    {
        $this->putJson('/api/v1/settings', ['values' => ['budgets.max_per_experiment' => 3000]])->assertOk();

        $this->assertSame(
            3000.0,
            app(SettingsService::class)->get('budgets.max_per_experiment', (int) $this->account->id),
        );
    }

    public function test_rejects_an_empty_write(): void
    {
        $this->putJson('/api/v1/settings', ['values' => []])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.fields.values.0', 'The values field is required.');
    }

    public function test_a_read_after_a_write_reflects_the_new_value(): void
    {
        $this->assertTrue($this->read()['features.chat']['value']);

        $this->putJson('/api/v1/settings', ['values' => ['features.chat' => false]])->assertOk();

        $this->assertFalse($this->read()['features.chat']['value']);
    }

    public function test_a_write_at_strategy_scope_stores_the_strategy_id(): void
    {
        $strategy = Strategy::factory()->create(['account_id' => $this->account->id]);

        $this->putJson("/api/v1/settings/strategies/{$strategy->id}", ['values' => ['features.tiktok' => true]])
            ->assertOk();

        $this->assertDatabaseHas('settings', [
            'scope' => SettingScope::STRATEGY->value,
            'scope_id' => $strategy->id,
            'key' => 'features.tiktok',
        ]);
    }

    public function test_a_strategy_write_does_not_change_another_strategys_effective_value(): void
    {
        $written = Strategy::factory()->create(['account_id' => $this->account->id]);
        $untouched = Strategy::factory()->create(['account_id' => $this->account->id]);

        $this->putJson("/api/v1/settings/strategies/{$written->id}", ['values' => ['features.chat' => false]])
            ->assertOk();

        $this->assertTrue($this->read((int) $untouched->id)['features.chat']['value']);
    }

    public function test_a_strategy_write_does_not_change_the_account_wide_value(): void
    {
        $strategy = Strategy::factory()->create(['account_id' => $this->account->id]);

        $this->putJson("/api/v1/settings/strategies/{$strategy->id}", ['values' => ['features.chat' => false]])
            ->assertOk();

        $this->assertTrue($this->read()['features.chat']['value']);
    }

    public function test_an_account_never_reads_another_accounts_settings(): void
    {
        $other = Account::factory()->create();
        Sanctum::actingAs($this->memberOf($other));
        $this->putJson('/api/v1/settings', ['values' => ['features.chat' => false]])->assertOk();

        Sanctum::actingAs($this->memberOf($this->account));

        $this->assertTrue($this->read()['features.chat']['value']);
    }

    public function test_a_write_by_one_account_leaves_the_other_accounts_row_alone(): void
    {
        $other = Account::factory()->create();
        Sanctum::actingAs($this->memberOf($other));
        $this->putJson('/api/v1/settings', ['values' => ['features.chat' => false]])->assertOk();

        Sanctum::actingAs($this->memberOf($this->account));
        $this->putJson('/api/v1/settings', ['values' => ['features.chat' => true]])->assertOk();

        Sanctum::actingAs($this->memberOf($other));
        $this->assertFalse($this->read()['features.chat']['value']);
    }

    /**
     * The strategy id arrives as a bare route parameter, so nothing ties it to the caller's
     * account unless the write path checks ownership.
     */
    public function test_an_account_cannot_write_settings_onto_another_accounts_strategy(): void
    {
        $other = Account::factory()->create();
        $strategy = Strategy::factory()->create(['account_id' => $other->id]);

        $this->putJson("/api/v1/settings/strategies/{$strategy->id}", ['values' => ['features.chat' => false]])
            ->assertNotFound();

        Sanctum::actingAs($this->memberOf($other));
        $this->assertTrue($this->read((int) $strategy->id)['features.chat']['value']);
    }

    private function read(?int $strategyId = null): array
    {
        $route = $strategyId === null ? '/api/v1/settings' : "/api/v1/settings/strategies/{$strategyId}";

        return $this->getJson($route)->assertOk()->json('result');
    }

    private function memberOf(Account $account): User
    {
        $user = User::factory()->create();
        $account->users()->attach($user);

        return $user;
    }
}
