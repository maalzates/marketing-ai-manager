<?php

declare(strict_types=1);

namespace Tests\Feature\Experiments;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Experiments\Domain\Enums\MetaAdsRule;
use App\Modules\Experiments\Domain\Enums\WarningCode;
use App\Modules\Knowledge\Domain\Enums\KnowledgeType;
use App\Modules\Knowledge\Infrastructure\Persistence\KnowledgeEntry;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Carbon\CarbonImmutable;
use Database\Seeders\DomainKnowledgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The warnings are the whole point of the module: an experiment carries Meta's rules
 * instantiated with its own dates and its own money, read live from the knowledge base.
 * Two things are proved here — the arithmetic is right, and the numbers really do come off
 * the `domain_rule` entries rather than out of PHP constants.
 */
class ExperimentWarningsTest extends TestCase
{
    use RefreshDatabase;

    private Strategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-01 09:00:00'));

        $user = User::factory()->create();
        $account = Account::factory()->create(['owner_user_id' => $user->id]);
        $user->accounts()->attach($account);
        $this->strategy = Strategy::factory()->create([
            'account_id' => $account->id,
            'monthly_budget' => 3000,
        ]);

        Sanctum::actingAs($user);
    }

    public function test_the_learning_phase_warning_spans_the_seven_days_after_the_start(): void
    {
        $this->seed(DomainKnowledgeSeeder::class);

        $experimentId = $this->createExperiment();

        $this->assertDatabaseHas('experiment_warnings', [
            'experiment_id' => $experimentId,
            'code' => WarningCode::LearningPhaseWindow->value,
            'severity' => 'info',
            'applies_from' => '2026-09-01 00:00:00',
            'applies_to' => '2026-09-08 00:00:00',
        ]);

        $this->assertStringContainsString(
            'entre el 01/09/2026 y el 08/09/2026',
            $this->warnings($experimentId)[WarningCode::LearningPhaseWindow->value]['message'],
        );
    }

    public function test_the_minimum_evaluation_date_warning_lands_seven_days_after_the_start(): void
    {
        $this->seed(DomainKnowledgeSeeder::class);

        $experimentId = $this->createExperiment();

        $this->assertDatabaseHas('experiment_warnings', [
            'experiment_id' => $experimentId,
            'code' => WarningCode::MinimumEvaluationDate->value,
            'applies_from' => '2026-09-08 00:00:00',
        ]);

        $this->assertStringContainsString(
            'No evalúes este experimento antes del 08/09/2026',
            $this->warnings($experimentId)[WarningCode::MinimumEvaluationDate->value]['message'],
        );
    }

    public function test_the_minimum_daily_budget_is_the_target_cpa_times_fifty_over_seven(): void
    {
        $this->seed(DomainKnowledgeSeeder::class);

        $warning = $this->warnings($this->createExperiment())[WarningCode::MinimumDailyBudget->value];

        $this->assertStringContainsString(
            'el presupuesto diario mínimo matemático es 142.86 — (20.00 × 50) ÷ 7',
            $warning['message'],
        );
    }

    /**
     * The same experiment, the same code, one edited `domain_rule` entry: if the figure
     * moves with it the knowledge base is wired in, and if it does not the number was a
     * constant all along.
     */
    public function test_the_minimum_daily_budget_moves_with_the_knowledge_entry(): void
    {
        $this->seed(DomainKnowledgeSeeder::class);
        $this->publishRuleVersion(MetaAdsRule::MinimumDailyBudget, ['events_needed' => 100, 'window_days' => 7]);

        $warning = $this->warnings($this->createExperiment())[WarningCode::MinimumDailyBudget->value];

        $this->assertStringContainsString(
            'el presupuesto diario mínimo matemático es 285.71 — (20.00 × 100) ÷ 7',
            $warning['message'],
        );
    }

    public function test_an_underfunded_experiment_is_flagged_critical(): void
    {
        $this->seed(DomainKnowledgeSeeder::class);

        $warning = $this->warnings($this->createExperiment(['max_budget' => 500]))[WarningCode::MinimumDailyBudget->value];

        $this->assertSame('critical', $warning['severity']);
        $this->assertStringContainsString('Tu configuración da 50.00 al día, por debajo de ese mínimo', $warning['message']);
    }

    public function test_a_funded_experiment_is_told_its_budget_covers_the_minimum(): void
    {
        $this->seed(DomainKnowledgeSeeder::class);

        $warning = $this->warnings($this->createExperiment(['max_budget' => 1500]))[WarningCode::MinimumDailyBudget->value];

        $this->assertSame('warning', $warning['severity']);
        $this->assertStringContainsString('Tu configuración da 150.00 al día, que lo cubre.', $warning['message']);
    }

    public function test_an_expected_result_that_is_not_a_cost_per_action_gets_no_budget_warning(): void
    {
        $this->seed(DomainKnowledgeSeeder::class);

        $warnings = $this->warnings($this->createExperiment([
            'expected_result' => ['metric' => 'ctr', 'operator' => 'gte', 'value' => 2],
        ]));

        $this->assertArrayNotHasKey(WarningCode::MinimumDailyBudget->value, $warnings);
    }

    public function test_the_learning_phase_window_follows_the_knowledge_entry(): void
    {
        $this->seed(DomainKnowledgeSeeder::class);
        $this->publishRuleVersion(MetaAdsRule::LearningPhase, ['events_needed' => 60, 'window_days' => 10]);

        $experimentId = $this->createExperiment();

        $this->assertDatabaseHas('experiment_warnings', [
            'experiment_id' => $experimentId,
            'code' => WarningCode::LearningPhaseWindow->value,
            'applies_to' => '2026-09-11 00:00:00',
        ]);

        $this->assertStringContainsString(
            '~60 eventos de optimización en una ventana móvil de 10 días',
            $this->warnings($experimentId)[WarningCode::LearningPhaseWindow->value]['message'],
        );
    }

    public function test_creating_an_experiment_still_works_without_the_domain_rules(): void
    {
        $this->postJson($this->storeUrl(), $this->payload())
            ->assertCreated()
            ->assertJsonPath('result.code', 'EXP-001');
    }

    public function test_a_missing_domain_rule_is_declared_in_a_warning_of_its_own(): void
    {
        $warnings = $this->warnings($this->createExperiment());

        $this->assertSame('warning', $warnings[WarningCode::DomainRuleUnavailable->value]['severity']);
        $this->assertStringContainsString(
            'no se pudieron leer de la base de conocimiento las reglas meta-ads-01-learning-phase, '
            .'meta-ads-02-edits-reset-learning, meta-ads-03-minimum-budget, meta-ads-06-minimum-duration',
            $warnings[WarningCode::DomainRuleUnavailable->value]['message'],
        );
    }

    public function test_the_documented_defaults_are_used_when_the_domain_rules_are_missing(): void
    {
        $warnings = $this->warnings($this->createExperiment());

        $this->assertStringContainsString(
            'el presupuesto diario mínimo matemático es 142.86 — (20.00 × 50) ÷ 7',
            $warnings[WarningCode::MinimumDailyBudget->value]['message'],
        );
    }

    public function test_the_defaulted_rules_warning_is_absent_once_the_knowledge_base_answers(): void
    {
        $this->seed(DomainKnowledgeSeeder::class);

        $this->assertArrayNotHasKey(
            WarningCode::DomainRuleUnavailable->value,
            $this->warnings($this->createExperiment()),
        );
    }

    public function test_an_organic_experiment_carries_no_meta_ads_warnings(): void
    {
        $this->seed(DomainKnowledgeSeeder::class);

        $experimentId = $this->createExperiment([
            'type' => 'organic',
            'max_budget' => null,
            'expected_result' => ['metric' => 'engagement_rate', 'operator' => 'gte', 'value' => 3],
        ]);

        $this->assertSame([], $this->warnings($experimentId));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createExperiment(array $overrides = []): int
    {
        return (int) $this->postJson($this->storeUrl(), $this->payload($overrides))
            ->assertCreated()
            ->json('result.id');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function warnings(int $experimentId): array
    {
        return collect($this->getJson("/api/v1/experiments/{$experimentId}/warnings")->assertOk()->json('result'))
            ->keyBy('code')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function publishRuleVersion(MetaAdsRule $rule, array $metadata): void
    {
        KnowledgeEntry::factory()->create([
            'type' => KnowledgeType::DomainRule,
            'key' => $rule->value,
            'locale' => 'es',
            'version' => 2,
            'metadata' => $metadata,
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
            'ends_at' => '2026-09-11',
            'max_budget' => 500,
            'status' => 'draft',
            ...$overrides,
        ];
    }
}
