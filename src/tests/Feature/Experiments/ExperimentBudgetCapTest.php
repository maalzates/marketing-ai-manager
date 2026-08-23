<?php

declare(strict_types=1);

namespace Tests\Feature\Experiments;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Experiments\Application\DTO\CreateExperimentDTO;
use App\Modules\Experiments\Application\Services\ExperimentService;
use App\Modules\Experiments\Domain\Enums\ExperimentPlatform;
use App\Modules\Experiments\Domain\Enums\ExperimentStatus;
use App\Modules\Experiments\Domain\Enums\ExperimentType;
use App\Modules\Experiments\Domain\Exceptions\ExperimentBudgetExceedsCapException;
use App\Modules\Experiments\Domain\Exceptions\ExperimentBudgetNotVerifiableException;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Carbon\CarbonImmutable;
use Database\Seeders\DomainKnowledgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Two ceilings, both enforced by the Service: the account's per-experiment cap and what
 * the owning strategy still has left this month. The second one is the one that can rot
 * silently — it reads committed spend out of the Experiments repository, and if the
 * container ever handed the module a null object reporting zero, every check here would
 * pass while the strategy quietly overspent.
 */
class ExperimentBudgetCapTest extends TestCase
{
    use RefreshDatabase;

    private const string CAP_KEY = 'budgets.max_per_experiment';

    private Account $account;

    private Strategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-01 09:00:00'));
        $this->seed(DomainKnowledgeSeeder::class);

        $user = User::factory()->create();
        $this->account = Account::factory()->create(['owner_user_id' => $user->id]);
        $user->accounts()->attach($this->account);
        $this->strategy = Strategy::factory()->create([
            'account_id' => $this->account->id,
            'monthly_budget' => 500,
        ]);

        Sanctum::actingAs($user);
    }

    public function test_rejects_a_max_budget_above_the_account_cap(): void
    {
        $this->capPerExperimentAt(100);

        $this->postJson($this->storeUrl(), $this->payload(['max_budget' => 150]))
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.message',
                'El presupuesto solicitado (150.00) supera el tope por experimento de la cuenta (100.00).',
            );

        $this->assertDatabaseCount('experiments', 0);
    }

    public function test_accepts_a_max_budget_equal_to_the_account_cap(): void
    {
        $this->capPerExperimentAt(100);

        $this->postJson($this->storeUrl(), $this->payload(['max_budget' => 100]))->assertCreated();

        $this->assertDatabaseHas('experiments', ['code' => 'EXP-001', 'max_budget' => '100.00']);
    }

    public function test_rejects_a_max_budget_above_the_account_cap_outside_http(): void
    {
        $this->capPerExperimentAt(100);

        $this->expectException(ExperimentBudgetExceedsCapException::class);
        $this->expectExceptionMessage('El presupuesto solicitado (150.00) supera el tope por experimento de la cuenta (100.00).');

        $this->app->make(ExperimentService::class)->create($this->dto(['max_budget' => 150.0]));
    }

    public function test_rejects_a_max_budget_above_what_the_strategy_has_left_this_month(): void
    {
        $this->commitBudget(500.0);

        $this->postJson($this->storeUrl(), $this->payload(['max_budget' => 100]))
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.message',
                'El presupuesto solicitado (100.00) supera lo que le queda a la estrategia este mes (0.00).',
            );

        $this->assertDatabaseCount('experiments', 1);
    }

    public function test_rejects_a_max_budget_above_the_strategy_remainder_outside_http(): void
    {
        $this->commitBudget(500.0);

        $this->expectException(ExperimentBudgetExceedsCapException::class);
        $this->expectExceptionMessage('El presupuesto solicitado (100.00) supera lo que le queda a la estrategia este mes (0.00).');

        $this->app->make(ExperimentService::class)->create($this->dto(['max_budget' => 100.0]));
    }

    public function test_accepts_a_max_budget_that_fits_in_the_strategy_remainder(): void
    {
        $this->commitBudget(400.0);

        $this->postJson($this->storeUrl(), $this->payload(['max_budget' => 100]))->assertCreated();

        $this->assertDatabaseCount('experiments', 2);
    }

    public function test_frees_the_budget_of_an_experiment_that_no_longer_commits_it(): void
    {
        $this->commitBudget(500.0, ExperimentStatus::Completed);

        $this->postJson($this->storeUrl(), $this->payload(['max_budget' => 500]))->assertCreated();
    }

    public function test_ignores_experiments_that_start_in_another_month(): void
    {
        $this->commitBudget(500.0, startsAt: CarbonImmutable::parse('2026-08-15'));

        $this->postJson($this->storeUrl(), $this->payload(['max_budget' => 500]))->assertCreated();
    }

    public function test_refuses_an_uncapped_ads_experiment_under_a_budgeted_strategy(): void
    {
        $this->postJson($this->storeUrl(), $this->payload(['max_budget' => null]))
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.message',
                'Esta estrategia tiene un presupuesto mensual, así que un experimento de ads necesita su '
                .'propio presupuesto máximo. Sin él no hay forma de comprobar que el mes cabe en el tope.',
            );

        $this->assertDatabaseCount('experiments', 0);
    }

    public function test_refuses_to_guess_when_the_strategy_already_holds_an_uncapped_experiment(): void
    {
        $this->commitBudget(null);

        $this->expectException(ExperimentBudgetNotVerifiableException::class);

        $this->app->make(ExperimentService::class)->create($this->dto(['max_budget' => 10.0]));
    }

    /**
     * Strategies binds a null-object workload provider with `bindIf` so it can boot alone.
     * If Experiments' real adapter ever stopped beating it, this deletion would succeed and
     * take every verdict recorded under the strategy with it.
     */
    public function test_a_strategy_carrying_experiments_cannot_be_deleted(): void
    {
        $this->commitBudget(100.0);

        $this->deleteJson("/api/v1/strategies/{$this->strategy->id}")
            ->assertStatus(409)
            ->assertJsonPath(
                'errors.message',
                'This strategy still has experiments recorded under it and cannot be deleted. Archive it instead.',
            );

        $this->assertDatabaseHas('strategies', ['id' => $this->strategy->id]);
    }

    private function capPerExperimentAt(float $cap): void
    {
        $response = $this->putJson('/api/v1/settings', ['values' => [self::CAP_KEY => $cap]])->assertOk();

        $this->assertEquals($cap, $response->json('result')[self::CAP_KEY]['value']);
    }

    private function commitBudget(
        ?float $maxBudget,
        ExperimentStatus $status = ExperimentStatus::Running,
        ?CarbonImmutable $startsAt = null,
    ): Experiment {
        return Experiment::factory()->create([
            'account_id' => $this->account->id,
            'strategy_id' => $this->strategy->id,
            'max_budget' => $maxBudget,
            'status' => $status,
            'starts_at' => $startsAt ?? CarbonImmutable::parse('2026-09-03'),
        ]);
    }

    private function storeUrl(): string
    {
        return "/api/v1/strategies/{$this->strategy->id}/experiments";
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'type' => 'ads',
            'platform' => 'instagram',
            'title' => 'Creativo A contra creativo B',
            'hypothesis' => 'El creativo con testimonio baja el CPA.',
            'expected_result' => ['metric' => 'cpa', 'operator' => 'lte', 'value' => 20],
            'starts_at' => '2026-09-01',
            'ends_at' => '2026-09-15',
            'max_budget' => 100,
            'status' => 'draft',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function dto(array $overrides = []): CreateExperimentDTO
    {
        return new CreateExperimentDTO(
            (int) $this->account->id,
            (int) $this->strategy->id,
            ExperimentType::Ads,
            ExperimentPlatform::Instagram,
            'Desde un job',
            'Saltarse el FormRequest.',
            ['metric' => 'cpa', 'operator' => 'lte', 'value' => 20],
            CarbonImmutable::parse('2026-09-01'),
            CarbonImmutable::parse('2026-09-15'),
            $overrides['max_budget'] ?? 100.0,
            [],
            ExperimentStatus::Draft,
        );
    }
}
