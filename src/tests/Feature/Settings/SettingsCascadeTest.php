<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Settings\Application\Services\SettingsService;
use App\Modules\Settings\Infrastructure\Persistence\Setting;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The cascade is strategy → account → global → the declared default in config/settings.php.
 * Each test adds one level and asserts both the effective value and where it came from.
 */
class SettingsCascadeTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'ai.limits.max_output_tokens';

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create();
        $user = User::factory()->create();
        $this->account->users()->attach($user);
        Sanctum::actingAs($user);
    }

    public function test_a_key_with_no_stored_row_resolves_to_the_declared_default(): void
    {
        $setting = $this->effective(self::KEY);

        $this->assertSame(config('settings.ai.limits.max_output_tokens'), $setting['value']);
        $this->assertSame('default', $setting['scope']);
    }

    public function test_a_global_row_overrides_the_declared_default(): void
    {
        Setting::factory()->create(['key' => self::KEY, 'value' => 2048]);

        $setting = $this->effective(self::KEY);

        $this->assertSame(2048, $setting['value']);
        $this->assertSame('global', $setting['scope']);
    }

    public function test_an_account_row_overrides_the_global_row(): void
    {
        Setting::factory()->create(['key' => self::KEY, 'value' => 2048]);
        Setting::factory()->forAccount((int) $this->account->id)->create(['key' => self::KEY, 'value' => 1024]);

        $setting = $this->effective(self::KEY);

        $this->assertSame(1024, $setting['value']);
        $this->assertSame('account', $setting['scope']);
    }

    public function test_a_strategy_row_overrides_the_account_row(): void
    {
        $strategy = Strategy::factory()->create(['account_id' => $this->account->id]);
        Setting::factory()->forAccount((int) $this->account->id)->create(['key' => self::KEY, 'value' => 1024]);
        Setting::factory()->forStrategy((int) $strategy->id)->create(['key' => self::KEY, 'value' => 512]);

        $setting = $this->effective(self::KEY, (int) $strategy->id);

        $this->assertSame(512, $setting['value']);
        $this->assertSame('strategy', $setting['scope']);
    }

    public function test_a_key_the_strategy_does_not_override_still_falls_back_to_the_account(): void
    {
        $strategy = Strategy::factory()->create(['account_id' => $this->account->id]);
        Setting::factory()->forAccount((int) $this->account->id)->create(['key' => self::KEY, 'value' => 1024]);
        Setting::factory()->forStrategy((int) $strategy->id)->create(['key' => 'features.chat', 'value' => false]);

        $setting = $this->effective(self::KEY, (int) $strategy->id);

        $this->assertSame(1024, $setting['value']);
        $this->assertSame('account', $setting['scope']);
    }

    /**
     * Read over HTTP the fraction is gone — JSON has no float — so the guarantee is asserted
     * where it is consumed: the services that compare a budget against this cap.
     */
    public function test_a_float_default_keeps_its_declared_type_when_stored_as_a_whole_number(): void
    {
        Setting::factory()->forAccount((int) $this->account->id)
            ->create(['key' => 'budgets.max_per_experiment', 'value' => 3000]);

        $this->assertSame(
            3000.0,
            app(SettingsService::class)->get('budgets.max_per_experiment', (int) $this->account->id),
        );
    }

    /**
     * @return array{value: mixed, scope: string}
     */
    private function effective(string $key, ?int $strategyId = null): array
    {
        $route = $strategyId === null ? '/api/v1/settings' : "/api/v1/settings/strategies/{$strategyId}";

        return $this->getJson($route)->assertOk()->json('result')[$key];
    }
}
