<?php

declare(strict_types=1);

namespace Tests\Feature\Proposals;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Audit\Infrastructure\Persistence\ActionLog;
use App\Modules\Experiments\Domain\Enums\Verdict;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use App\Modules\Proposals\Domain\Enums\ProposalStatus;
use App\Modules\Proposals\Infrastructure\Persistence\Proposal;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The product's central promise: the assistant proposes and the human decides. A decision
 * is valid exactly once, on a pending, unexpired proposal — a second click on the same
 * card must be refused rather than run the mutation again.
 */
class ProposalDecisionTest extends TestCase
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

    public function test_accepting_a_proposal_executes_it_and_marks_it_executed(): void
    {
        $proposal = $this->closeExperimentProposal();

        $this->postJson("/api/v1/proposals/{$proposal->id}/accept")
            ->assertOk()
            ->assertJsonPath('result.status', ProposalStatus::Executed->value)
            ->assertJsonPath('result.execution_result.verdict', Verdict::DidNotWork->value);

        $this->assertSame(Verdict::DidNotWork, $this->experiment->refresh()->verdict);
    }

    public function test_accepting_the_same_proposal_twice_executes_it_once(): void
    {
        $proposal = $this->closeExperimentProposal();

        $this->postJson("/api/v1/proposals/{$proposal->id}/accept")->assertOk();

        $this->postJson("/api/v1/proposals/{$proposal->id}/accept")
            ->assertStatus(409)
            ->assertJsonPath(
                'errors.message',
                'Esta propuesta ya está en estado "executed" y no admite otra decisión.',
            );

        $this->assertSame(1, ActionLog::query()->where('action', 'experiment.verdict_confirmed')->count());
    }

    public function test_accepting_a_proposal_that_was_already_rejected_is_a_conflict(): void
    {
        $proposal = $this->closeExperimentProposal(['status' => ProposalStatus::Rejected]);

        $this->postJson("/api/v1/proposals/{$proposal->id}/accept")
            ->assertStatus(409)
            ->assertJsonPath(
                'errors.message',
                'Esta propuesta ya está en estado "rejected" y no admite otra decisión.',
            );

        $this->assertNull($this->experiment->refresh()->verdict);
    }

    public function test_accepting_an_expired_proposal_is_a_conflict(): void
    {
        $proposal = $this->closeExperimentProposal(['expires_at' => CarbonImmutable::parse('2026-09-09 06:00:00')]);

        $this->postJson("/api/v1/proposals/{$proposal->id}/accept")
            ->assertStatus(409)
            ->assertJsonPath(
                'errors.message',
                'Esta propuesta caducó el 09/09/2026 06:00; vuelve a pedirla si sigue teniendo sentido.',
            );

        $this->assertNull($this->experiment->refresh()->verdict);
    }

    public function test_an_expired_proposal_is_moved_to_expired_when_it_is_refused(): void
    {
        $proposal = $this->closeExperimentProposal(['expires_at' => CarbonImmutable::parse('2026-09-09 06:00:00')]);

        $this->postJson("/api/v1/proposals/{$proposal->id}/accept")->assertStatus(409);

        $this->assertDatabaseHas('proposals', ['id' => $proposal->id, 'status' => ProposalStatus::Expired->value]);
    }

    public function test_rejecting_a_proposal_marks_it_rejected_with_the_reason(): void
    {
        $proposal = $this->closeExperimentProposal();

        $this->postJson("/api/v1/proposals/{$proposal->id}/reject", ['reason' => 'Quiero darle otra semana.'])
            ->assertOk()
            ->assertJsonPath('result.status', ProposalStatus::Rejected->value)
            ->assertJsonPath('result.execution_result.reason', 'Quiero darle otra semana.');

        $this->assertDatabaseHas('proposals', [
            'id' => $proposal->id,
            'status' => ProposalStatus::Rejected->value,
            'decided_by_user_id' => $this->user->id,
        ]);
    }

    public function test_rejecting_a_proposal_executes_nothing(): void
    {
        $proposal = $this->closeExperimentProposal();

        $this->postJson("/api/v1/proposals/{$proposal->id}/reject", ['reason' => 'Todavía no.'])->assertOk();

        $this->assertNull($this->experiment->refresh()->verdict);
        $this->assertSame('running', $this->experiment->refresh()->status->value);
        $this->assertSame(0, ActionLog::query()->where('action', 'experiment.verdict_confirmed')->count());
    }

    public function test_rejecting_a_proposal_that_was_already_decided_is_a_conflict(): void
    {
        $proposal = $this->closeExperimentProposal(['status' => ProposalStatus::Executed]);

        $this->postJson("/api/v1/proposals/{$proposal->id}/reject", ['reason' => 'Tarde.'])
            ->assertStatus(409);
    }

    public function test_accepting_a_proposal_logs_the_deciding_user(): void
    {
        $proposal = $this->closeExperimentProposal();

        $this->postJson("/api/v1/proposals/{$proposal->id}/accept")->assertOk();

        $this->assertDatabaseHas('action_logs', [
            'account_id' => $this->account->id,
            'user_id' => $this->user->id,
            'action' => 'proposal.accepted',
            'entity_type' => 'proposal',
            'entity_id' => $proposal->id,
        ]);
    }

    public function test_rejecting_a_proposal_logs_the_deciding_user(): void
    {
        $proposal = $this->closeExperimentProposal();

        $this->postJson("/api/v1/proposals/{$proposal->id}/reject", ['reason' => 'No me convence.'])->assertOk();

        $this->assertDatabaseHas('action_logs', [
            'account_id' => $this->account->id,
            'user_id' => $this->user->id,
            'action' => 'proposal.rejected',
            'entity_type' => 'proposal',
            'entity_id' => $proposal->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function closeExperimentProposal(array $overrides = []): Proposal
    {
        return Proposal::factory()->create([
            'account_id' => $this->account->id,
            'experiment_id' => $this->experiment->id,
            'payload' => ['verdict' => Verdict::DidNotWork->value, 'reason' => 'El CPA no bajó en tres semanas.'],
            ...$overrides,
        ]);
    }
}
