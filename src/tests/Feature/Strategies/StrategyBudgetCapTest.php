<?php

declare(strict_types=1);

namespace Tests\Feature\Strategies;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Brands\Infrastructure\Persistence\BrandProfile;
use App\Modules\Strategies\Application\DTO\CreateStrategyDTO;
use App\Modules\Strategies\Application\DTO\UpdateStrategyDTO;
use App\Modules\Strategies\Application\Services\StrategyService;
use App\Modules\Strategies\Domain\Exceptions\StrategyBudgetExceedsAccountCapException;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The cap lives in the Service, not in the FormRequest, because chat tools and jobs write
 * strategies too. Each door is checked separately: a rule that only the HTTP door enforces
 * is a rule the other two ignore.
 */
class StrategyBudgetCapTest extends TestCase
{
    use RefreshDatabase;

    private const string CAP_KEY = 'budgets.max_monthly_per_strategy';

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

    public function test_rejects_a_strategy_budget_above_the_account_cap(): void
    {
        $this->capMonthlyBudgetAt(300);

        $this->postJson('/api/v1/strategies', $this->payload(['monthly_budget' => 300.01]))
            ->assertStatus(422)
            ->assertJsonPath('errors.message', 'A strategy may not budget more than 300 per month.');

        $this->assertDatabaseCount('strategies', 0);
    }

    public function test_accepts_a_strategy_budget_equal_to_the_account_cap(): void
    {
        $this->capMonthlyBudgetAt(300);

        $this->postJson('/api/v1/strategies', $this->payload(['monthly_budget' => 300]))->assertCreated();

        $this->assertDatabaseHas('strategies', ['monthly_budget' => '300.00']);
    }

    public function test_applies_the_declared_default_cap_when_the_account_sets_none(): void
    {
        $this->postJson('/api/v1/strategies', $this->payload(['monthly_budget' => 5000.01]))
            ->assertStatus(422)
            ->assertJsonPath('errors.message', 'A strategy may not budget more than 5000 per month.');
    }

    public function test_rejects_raising_an_existing_budget_above_the_account_cap(): void
    {
        $this->capMonthlyBudgetAt(300);
        $strategy = $this->ownStrategy(['monthly_budget' => 200]);

        $this->putJson("/api/v1/strategies/{$strategy->id}", ['monthly_budget' => 900])
            ->assertStatus(422)
            ->assertJsonPath('errors.message', 'A strategy may not budget more than 300 per month.');

        $this->assertDatabaseHas('strategies', ['id' => $strategy->id, 'monthly_budget' => '200.00']);
    }

    public function test_enforces_the_cap_when_a_strategy_is_created_outside_http(): void
    {
        $this->capMonthlyBudgetAt(300);

        $this->expectException(StrategyBudgetExceedsAccountCapException::class);
        $this->expectExceptionMessage('A strategy may not budget more than 300 per month.');

        $this->app->make(StrategyService::class)->create(new CreateStrategyDTO(
            (int) $this->account->id,
            (int) $this->brandProfile->id,
            'Desde un job',
            'Saltarse el FormRequest.',
            'cost_per_lead',
            900.0,
        ));
    }

    public function test_writes_no_row_when_the_cap_is_breached_outside_http(): void
    {
        $this->capMonthlyBudgetAt(300);

        try {
            $this->app->make(StrategyService::class)->create(new CreateStrategyDTO(
                (int) $this->account->id,
                (int) $this->brandProfile->id,
                'Desde un job',
                'Saltarse el FormRequest.',
                'cost_per_lead',
                900.0,
            ));
        } catch (StrategyBudgetExceedsAccountCapException) {
            // The assertion below is the behaviour; the exception is only how it surfaces.
        }

        $this->assertDatabaseCount('strategies', 0);
    }

    public function test_enforces_the_cap_when_a_budget_is_updated_outside_http(): void
    {
        $this->capMonthlyBudgetAt(300);
        $strategy = $this->ownStrategy(['monthly_budget' => 200]);

        $this->expectException(StrategyBudgetExceedsAccountCapException::class);

        $this->app->make(StrategyService::class)->update(new UpdateStrategyDTO(
            (int) $this->account->id,
            (int) $strategy->id,
            monthlyBudget: 900.0,
        ));
    }

    public function test_leaves_a_strategy_without_a_budget_alone(): void
    {
        $this->capMonthlyBudgetAt(300);

        $this->postJson('/api/v1/strategies', $this->payload(['monthly_budget' => null]))->assertCreated();

        $this->assertDatabaseHas('strategies', ['monthly_budget' => null]);
    }

    private function capMonthlyBudgetAt(float $cap): void
    {
        $response = $this->putJson('/api/v1/settings', ['values' => [self::CAP_KEY => $cap]])->assertOk();

        // A whole float travels back through JSON as an int; the registry keeps the type.
        $this->assertEquals($cap, $response->json('result')[self::CAP_KEY]['value']);
        $this->assertSame('account', $response->json('result')[self::CAP_KEY]['scope']);
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
