<?php

declare(strict_types=1);

namespace Tests\Feature\Experiments;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Experiments\Application\Services\VerdictService;
use App\Modules\Experiments\Domain\Enums\Verdict;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use App\Modules\Experiments\Infrastructure\Persistence\ExperimentMetric;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Carbon\CarbonImmutable;
use Database\Seeders\DomainKnowledgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The suggestion is arithmetic over the stored daily series and the confirmation is the
 * human's. The two are deliberately separate: the assistant may be wrong, and what ends up
 * in the history is what the user said, not what the code guessed.
 */
class ExperimentVerdictTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private User $user;

    private Strategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-10 06:00:00'));
        $this->seed(DomainKnowledgeSeeder::class);

        $this->user = User::factory()->create();
        $this->account = Account::factory()->create(['owner_user_id' => $this->user->id]);
        $this->user->accounts()->attach($this->account);
        $this->strategy = Strategy::factory()->create(['account_id' => $this->account->id]);

        Sanctum::actingAs($this->user);
    }

    public function test_a_cost_below_the_lte_threshold_suggests_that_it_worked(): void
    {
        $experiment = $this->judgeableAdsExperiment(['metric' => 'cpa', 'operator' => 'lte', 'value' => 20]);
        $this->recordDays($experiment, 7, spend: 10.0, conversions: 10);

        $suggestion = $this->app->make(VerdictService::class)->suggest((int) $experiment->id, (int) $this->account->id);

        $this->assertSame(Verdict::Worked, $suggestion->verdict);
        $this->assertSame(1.0, $suggestion->actualValue);
        $this->assertSame(7, $suggestion->daysWithData);
    }

    public function test_a_cost_above_the_lte_threshold_suggests_that_it_did_not_work(): void
    {
        $experiment = $this->judgeableAdsExperiment(['metric' => 'cpa', 'operator' => 'lte', 'value' => 20]);
        $this->recordDays($experiment, 7, spend: 100.0, conversions: 1);

        $suggestion = $this->app->make(VerdictService::class)->suggest((int) $experiment->id, (int) $this->account->id);

        $this->assertSame(Verdict::DidNotWork, $suggestion->verdict);
        $this->assertSame(100.0, $suggestion->actualValue);
    }

    public function test_a_rate_above_the_gte_threshold_suggests_that_it_worked(): void
    {
        $experiment = $this->organicExperiment(['metric' => 'engagement_rate', 'operator' => 'gte', 'value' => 3]);
        $this->recordDays($experiment, 4, engagement: 300, impressions: 10000);

        $suggestion = $this->app->make(VerdictService::class)->suggest((int) $experiment->id, (int) $this->account->id);

        $this->assertSame(Verdict::Worked, $suggestion->verdict);
        $this->assertSame(3.0, $suggestion->actualValue);
    }

    public function test_a_rate_below_the_gte_threshold_suggests_that_it_did_not_work(): void
    {
        $experiment = $this->organicExperiment(['metric' => 'engagement_rate', 'operator' => 'gte', 'value' => 3]);
        $this->recordDays($experiment, 4, engagement: 100, impressions: 10000);

        $suggestion = $this->app->make(VerdictService::class)->suggest((int) $experiment->id, (int) $this->account->id);

        $this->assertSame(Verdict::DidNotWork, $suggestion->verdict);
        $this->assertSame(1.0, $suggestion->actualValue);
    }

    public function test_an_experiment_without_metrics_is_inconclusive(): void
    {
        $experiment = $this->judgeableAdsExperiment(['metric' => 'cpa', 'operator' => 'lte', 'value' => 20]);

        $suggestion = $this->app->make(VerdictService::class)->suggest((int) $experiment->id, (int) $this->account->id);

        $this->assertSame(Verdict::Inconclusive, $suggestion->verdict);
        $this->assertSame(0, $suggestion->daysWithData);
    }

    public function test_an_ads_experiment_with_fewer_than_seven_days_of_data_is_inconclusive(): void
    {
        $experiment = $this->judgeableAdsExperiment(['metric' => 'cpa', 'operator' => 'lte', 'value' => 20]);
        $this->recordDays($experiment, 6, spend: 10.0, conversions: 10);

        $suggestion = $this->app->make(VerdictService::class)->suggest((int) $experiment->id, (int) $this->account->id);

        $this->assertSame(Verdict::Inconclusive, $suggestion->verdict);
        $this->assertStringContainsString('Solo hay 6 días de datos', $suggestion->reasoning);
    }

    public function test_an_ads_experiment_still_inside_its_learning_window_is_inconclusive(): void
    {
        $experiment = $this->judgeableAdsExperiment(['metric' => 'cpa', 'operator' => 'lte', 'value' => 20]);
        $experiment->update(['learning_phase_ends_at' => CarbonImmutable::parse('2026-09-20')]);
        $this->recordDays($experiment, 7, spend: 10.0, conversions: 10);

        $suggestion = $this->app->make(VerdictService::class)->suggest((int) $experiment->id, (int) $this->account->id);

        $this->assertSame(Verdict::Inconclusive, $suggestion->verdict);
        $this->assertStringContainsString('hasta el 20/09/2026', $suggestion->reasoning);
    }

    public function test_a_metric_whose_denominator_is_zero_is_inconclusive(): void
    {
        $experiment = $this->judgeableAdsExperiment(['metric' => 'cpa', 'operator' => 'lte', 'value' => 20]);
        $this->recordDays($experiment, 7, spend: 10.0, conversions: 0);

        $suggestion = $this->app->make(VerdictService::class)->suggest((int) $experiment->id, (int) $this->account->id);

        $this->assertSame(Verdict::Inconclusive, $suggestion->verdict);
        $this->assertNull($suggestion->actualValue);
    }

    public function test_confirming_a_verdict_completes_the_experiment(): void
    {
        $experiment = $this->judgeableAdsExperiment(['metric' => 'cpa', 'operator' => 'lte', 'value' => 20]);

        $this->postJson("/api/v1/experiments/{$experiment->id}/verdict", [
            'verdict' => 'worked',
            'reason' => 'El CPA bajó y se mantuvo bajo tres semanas.',
        ])
            ->assertOk()
            ->assertJsonPath('result.verdict', 'worked')
            ->assertJsonPath('result.status', 'completed');

        $this->assertDatabaseHas('experiments', [
            'id' => $experiment->id,
            'verdict' => 'worked',
            'verdict_reason' => 'El CPA bajó y se mantuvo bajo tres semanas.',
            'status' => 'completed',
        ]);
        $this->assertNotNull($experiment->refresh()->verdict_confirmed_at);
    }

    public function test_the_user_may_record_a_verdict_that_contradicts_the_suggestion(): void
    {
        $experiment = $this->judgeableAdsExperiment(['metric' => 'cpa', 'operator' => 'lte', 'value' => 20]);
        $this->recordDays($experiment, 7, spend: 100.0, conversions: 1);

        $this->assertSame(
            Verdict::DidNotWork,
            $this->app->make(VerdictService::class)->suggest((int) $experiment->id, (int) $this->account->id)->verdict,
        );

        $this->postJson("/api/v1/experiments/{$experiment->id}/verdict", [
            'verdict' => 'worked',
            'reason' => 'El CPA fue alto pero el LTV de esos clientes lo justifica.',
        ])->assertOk();

        $this->assertSame(Verdict::Worked, $experiment->refresh()->verdict);
    }

    public function test_confirming_a_verdict_before_the_end_date_marks_it_closed_early(): void
    {
        $experiment = $this->judgeableAdsExperiment(['metric' => 'cpa', 'operator' => 'lte', 'value' => 20]);
        $experiment->update(['ends_at' => CarbonImmutable::parse('2026-09-30')]);

        $this->postJson("/api/v1/experiments/{$experiment->id}/verdict", [
            'verdict' => 'inconclusive',
            'reason' => 'Se cortó por falta de presupuesto.',
        ])->assertOk();

        $this->assertTrue($experiment->refresh()->closed_early);
    }

    public function test_confirming_a_verdict_names_the_deciding_user_in_the_action_log(): void
    {
        $experiment = $this->judgeableAdsExperiment(['metric' => 'cpa', 'operator' => 'lte', 'value' => 20]);

        $this->postJson("/api/v1/experiments/{$experiment->id}/verdict", [
            'verdict' => 'worked',
            'reason' => 'Se cumplió el objetivo.',
        ])->assertOk();

        $this->assertDatabaseHas('action_logs', [
            'account_id' => $this->account->id,
            'user_id' => $this->user->id,
            'action' => 'experiment.verdict_confirmed',
            'entity_type' => 'experiment',
            'entity_id' => $experiment->id,
        ]);
    }

    public function test_a_verdict_without_a_reason_is_rejected(): void
    {
        $experiment = $this->judgeableAdsExperiment(['metric' => 'cpa', 'operator' => 'lte', 'value' => 20]);

        $this->postJson("/api/v1/experiments/{$experiment->id}/verdict", ['verdict' => 'worked'])
            ->assertStatus(422)
            ->assertJsonPath('errors.fields.reason.0', 'The reason field is required.');

        $this->assertNull($experiment->refresh()->verdict);
    }

    /**
     * @param  array<string, mixed>  $expectedResult
     */
    private function judgeableAdsExperiment(array $expectedResult): Experiment
    {
        return Experiment::factory()->running()->create([
            'account_id' => $this->account->id,
            'strategy_id' => $this->strategy->id,
            'expected_result' => $expectedResult,
            'starts_at' => CarbonImmutable::parse('2026-08-01'),
            'ends_at' => CarbonImmutable::parse('2026-08-31'),
            'learning_phase_ends_at' => CarbonImmutable::parse('2026-08-08'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $expectedResult
     */
    private function organicExperiment(array $expectedResult): Experiment
    {
        return Experiment::factory()->organic()->running()->create([
            'account_id' => $this->account->id,
            'strategy_id' => $this->strategy->id,
            'expected_result' => $expectedResult,
            'starts_at' => CarbonImmutable::parse('2026-08-01'),
            'ends_at' => CarbonImmutable::parse('2026-08-31'),
        ]);
    }

    private function recordDays(
        Experiment $experiment,
        int $days,
        float $spend = 0.0,
        int $conversions = 0,
        int $engagement = 0,
        int $impressions = 0,
    ): void {
        foreach (range(0, $days - 1) as $offset) {
            ExperimentMetric::factory()->create([
                'account_id' => $experiment->account_id,
                'experiment_id' => $experiment->id,
                'date' => CarbonImmutable::parse('2026-08-10')->addDays($offset),
                'spend' => $spend,
                'conversions' => $conversions,
                'engagement' => $engagement,
                'impressions' => $impressions,
            ]);
        }
    }
}
