<?php

declare(strict_types=1);

namespace Tests\Feature\Proposals;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Experiments\Domain\Enums\Verdict;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use App\Modules\Proposals\Domain\Enums\ProposalStatus;
use App\Modules\Proposals\Domain\Enums\ProposalType;
use App\Modules\Proposals\Infrastructure\Persistence\Proposal;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * What "executed" actually means, per type. `close_experiment` is wired end to end here:
 * accepting it is what writes the verdict the assistant later learns from. The types that
 * still wait on Campaigns and Content must say so with the documented status instead of
 * collapsing into a 500 — and whatever the failure, the proposal may never be left sitting
 * on `accepted` with the button still live.
 */
class ProposalExecutionTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private User $user;

    private Experiment $experiment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-10 06:00:00'));

        $this->user = User::factory()->create();
        $this->account = Account::factory()->create(['owner_user_id' => $this->user->id]);
        $this->user->accounts()->attach($this->account);
        $this->experiment = Experiment::factory()->running()->create([
            'account_id' => $this->account->id,
            'strategy_id' => Strategy::factory()->create(['account_id' => $this->account->id]),
            'ends_at' => CarbonImmutable::parse('2026-09-30'),
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_accepting_a_close_experiment_proposal_completes_the_experiment(): void
    {
        $proposal = $this->proposal(ProposalType::CloseExperiment, [
            'verdict' => Verdict::Worked->value,
            'reason' => 'El CPA bajó de 30 a 12.',
        ]);

        $this->postJson("/api/v1/proposals/{$proposal->id}/accept")->assertOk();

        $this->assertDatabaseHas('experiments', [
            'id' => $this->experiment->id,
            'verdict' => Verdict::Worked->value,
            'verdict_reason' => 'El CPA bajó de 30 a 12.',
            'status' => 'completed',
            'closed_early' => true,
        ]);
    }

    public function test_the_verdict_written_by_a_proposal_enters_the_action_history(): void
    {
        $proposal = $this->proposal(ProposalType::CloseExperiment, [
            'verdict' => Verdict::Worked->value,
            'reason' => 'El CPA bajó de 30 a 12.',
        ]);

        $this->postJson("/api/v1/proposals/{$proposal->id}/accept")->assertOk();

        $this->assertDatabaseHas('action_logs', [
            'account_id' => $this->account->id,
            'user_id' => $this->user->id,
            'action' => 'experiment.verdict_confirmed',
            'entity_type' => 'experiment',
            'entity_id' => $this->experiment->id,
        ]);
    }

    public function test_the_execution_result_records_which_experiment_was_closed(): void
    {
        $proposal = $this->proposal(ProposalType::CloseExperiment, [
            'verdict' => Verdict::Inconclusive->value,
            'reason' => 'Sin datos suficientes.',
        ]);

        $this->postJson("/api/v1/proposals/{$proposal->id}/accept")
            ->assertOk()
            ->assertJsonPath('result.execution_result.experiment_id', $this->experiment->id)
            ->assertJsonPath('result.execution_result.verdict', Verdict::Inconclusive->value)
            ->assertJsonPath('result.execution_result.closed_at', '2026-09-10T06:00:00+00:00');
    }

    public function test_a_close_experiment_proposal_without_a_verdict_in_its_payload_fails(): void
    {
        $proposal = $this->proposal(ProposalType::CloseExperiment, ['reason' => 'Ciérralo y ya.']);

        $this->postJson("/api/v1/proposals/{$proposal->id}/accept")
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.message',
                'La propuesta de tipo "close_experiment" no trae "payload.verdict" y no se puede ejecutar.',
            );

        $this->assertNull($this->experiment->refresh()->verdict);
    }

    public function test_a_failed_execution_leaves_the_proposal_on_failed_with_the_reason(): void
    {
        $proposal = $this->proposal(ProposalType::CloseExperiment, ['reason' => 'Ciérralo y ya.']);

        $this->postJson("/api/v1/proposals/{$proposal->id}/accept")->assertStatus(422);

        $proposal->refresh();

        $this->assertSame(ProposalStatus::Failed, $proposal->status);
        $this->assertSame(
            'La propuesta de tipo "close_experiment" no trae "payload.verdict" y no se puede ejecutar.',
            $proposal->execution_result['reason'],
        );
    }

    public function test_a_close_experiment_proposal_that_names_no_experiment_fails(): void
    {
        $proposal = $this->proposal(
            ProposalType::CloseExperiment,
            ['verdict' => Verdict::Worked->value],
            ['experiment_id' => null],
        );

        $this->postJson("/api/v1/proposals/{$proposal->id}/accept")
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.message',
                'La propuesta de tipo "close_experiment" no trae "experiment_id" y no se puede ejecutar.',
            );

        $this->assertDatabaseHas('proposals', ['id' => $proposal->id, 'status' => ProposalStatus::Failed->value]);
    }

    public function test_the_executors_waiting_on_other_modules_say_so_instead_of_failing_internally(): void
    {
        $pending = [
            ProposalType::BudgetChange,
            ProposalType::PauseExperiment,
            ProposalType::ScheduleContent,
        ];

        foreach ($pending as $type) {
            $proposal = $this->proposal($type, ['new_daily_budget' => 30]);

            $this->postJson("/api/v1/proposals/{$proposal->id}/accept")
                ->assertStatus(501)
                ->assertJsonPath(
                    'errors.message',
                    sprintf('Todavía no se puede ejecutar una propuesta de tipo "%s" desde la aplicación.', $type->value),
                );

            $this->assertDatabaseHas('proposals', ['id' => $proposal->id, 'status' => ProposalStatus::Failed->value]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $overrides
     */
    private function proposal(ProposalType $type, array $payload, array $overrides = []): Proposal
    {
        return Proposal::factory()->ofType($type)->create([
            'account_id' => $this->account->id,
            'experiment_id' => $this->experiment->id,
            'payload' => $payload,
            ...$overrides,
        ]);
    }
}
