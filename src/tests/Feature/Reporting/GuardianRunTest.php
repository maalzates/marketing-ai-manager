<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Experiments\Domain\Enums\ExperimentStatus;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use App\Modules\Experiments\Infrastructure\Persistence\ExperimentMetric;
use App\Modules\Strategies\Domain\Enums\StrategyStatus;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Carbon\CarbonImmutable;
use Database\Seeders\DomainKnowledgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeTransport;
use Tests\Support\RecordingLlmClientFactory;
use Tests\TestCase;

/**
 * The guardián's restraint is the feature. A run that says nothing is the expected outcome,
 * so every test here counts what it did NOT do: proposals not raised, tokens not spent,
 * providers not called. The learning-window pair is core.md §10.6 — early performance is
 * volatility and must be ignored, money leaving with nothing coming back never is.
 */
class GuardianRunTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private Strategy $strategy;

    private Experiment $experiment;

    private FakeTransport $transport;

    private RecordingLlmClientFactory $llm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-10 07:00:00'));
        $this->seed(DomainKnowledgeSeeder::class);

        $this->transport = FakeTransport::silent()->install($this->app);
        $this->llm = RecordingLlmClientFactory::replaying('anthropic-text.json')->install($this->app);

        $user = User::factory()->create();
        $this->account = Account::factory()->create(['owner_user_id' => $user->id]);
        $user->accounts()->attach($this->account);

        $this->strategy = Strategy::factory()->create([
            'account_id' => $this->account->id,
            'status' => StrategyStatus::Active,
        ]);

        $this->experiment = Experiment::factory()->create([
            'account_id' => $this->account->id,
            'strategy_id' => $this->strategy->id,
            'status' => ExperimentStatus::Running,
            'code' => 'EXP-101',
            'starts_at' => CarbonImmutable::parse('2026-09-01'),
            'ends_at' => CarbonImmutable::parse('2026-09-30'),
            'learning_phase_ends_at' => CarbonImmutable::parse('2026-09-08'),
            'max_budget' => 1000.00,
            'expected_result' => ['metric' => 'cpa', 'operator' => 'lte', 'value' => 20.0],
        ]);

        Sanctum::actingAs($user);
    }

    public function test_a_healthy_experiment_produces_no_proposal_at_all(): void
    {
        $this->metrics(spend: 100.00, impressions: 20000, conversions: 10);

        $this->runGuardian();

        $this->assertDatabaseCount('proposals', 0);
        $this->assertSame(0, $this->guardianCompletions());
    }

    public function test_an_experiment_far_off_target_after_the_learning_window_is_proposed_for_closure(): void
    {
        $this->metrics(spend: 300.00, impressions: 20000, conversions: 2);

        $this->runGuardian();

        $this->assertDatabaseHas('proposals', [
            'account_id' => $this->account->id,
            'experiment_id' => $this->experiment->id,
            'type' => 'close_experiment',
            'origin' => 'guardian',
            'status' => 'pending',
        ]);
    }

    /** Inside Meta's learning window early performance is volatile by design — judging it is judging noise. */
    public function test_an_experiment_far_off_target_inside_the_learning_window_is_left_alone(): void
    {
        $this->insideTheLearningWindow();
        $this->metrics(spend: 300.00, impressions: 20000, conversions: 2);

        $this->runGuardian();

        $this->assertDatabaseCount('proposals', 0);
        $this->assertSame(0, $this->guardianCompletions());
    }

    /** The other half of §10.6: spending with zero delivery is never volatility, window or not. */
    public function test_an_experiment_spending_with_zero_delivery_is_proposed_even_inside_the_learning_window(): void
    {
        $this->insideTheLearningWindow();
        $this->metrics(spend: 120.00, impressions: 0, conversions: 0);

        $this->runGuardian();

        $this->assertDatabaseHas('proposals', [
            'experiment_id' => $this->experiment->id,
            'type' => 'close_experiment',
            'status' => 'pending',
        ]);
        $this->assertSame(1, $this->guardianCompletions());
    }

    public function test_a_strategy_with_no_active_experiments_costs_nothing(): void
    {
        $this->experiment->update(['status' => ExperimentStatus::Completed]);

        $this->runGuardian();

        $this->assertSame(0, $this->llm->callCount());
        $this->assertSame(0, $this->transport->requestCount());
        $this->assertDatabaseCount('proposals', 0);
        $this->assertDatabaseMissing('action_logs', ['action' => 'guardian.run']);
    }

    public function test_a_second_run_the_same_day_raises_no_second_proposal(): void
    {
        $this->metrics(spend: 300.00, impressions: 20000, conversions: 2);

        $this->runGuardian();
        $this->travelTo(CarbonImmutable::parse('2026-09-10 19:00:00'));
        $this->runGuardian();

        $this->assertDatabaseCount('proposals', 1);
    }

    public function test_a_second_run_the_same_day_asks_the_model_nothing_further(): void
    {
        $this->metrics(spend: 300.00, impressions: 20000, conversions: 2);

        $this->runGuardian();
        $this->runGuardian();

        $this->assertSame(1, $this->guardianCompletions());
    }

    public function test_detection_never_calls_a_provider_for_an_experiment_with_no_campaign(): void
    {
        $this->metrics(spend: 300.00, impressions: 20000, conversions: 2);

        $this->runGuardian();

        $this->assertSame(0, $this->transport->requestCount());
    }

    public function test_does_not_run_the_guardian_on_another_accounts_strategy(): void
    {
        $foreign = Strategy::factory()->create([
            'account_id' => Account::factory()->create()->id,
            'status' => StrategyStatus::Active,
        ]);

        $this->postJson("/api/v1/strategies/{$foreign->id}/guardian/run")->assertNotFound();

        $this->assertDatabaseCount('proposals', 0);
    }

    /**
     * Only the completions that wrote a proposal's rationale. The same job also narrates a
     * periodic report under the same task, so the prompt is the one thing that tells them
     * apart — and paying for a rationale nobody asked for is exactly what this counts.
     */
    private function guardianCompletions(): int
    {
        return count(array_filter(
            range(0, $this->llm->callCount() - 1),
            fn (int $index): bool => str_contains($this->llm->promptText($index), 'El guardián detectó estas anomalías'),
        ));
    }

    private function runGuardian(): void
    {
        $this->postJson("/api/v1/strategies/{$this->strategy->id}/guardian/run")->assertAccepted();
    }

    private function insideTheLearningWindow(): void
    {
        $this->experiment->update(['learning_phase_ends_at' => CarbonImmutable::parse('2026-09-20')]);
    }

    private function metrics(float $spend, int $impressions, int $conversions): void
    {
        ExperimentMetric::factory()->create([
            'account_id' => $this->account->id,
            'experiment_id' => $this->experiment->id,
            'date' => CarbonImmutable::parse('2026-09-09'),
            'spend' => $spend,
            'impressions' => $impressions,
            'reach' => $impressions,
            'clicks' => 100,
            'conversions' => $conversions,
            'cpa' => $conversions > 0 ? $spend / $conversions : null,
        ]);
    }
}
