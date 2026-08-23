<?php

declare(strict_types=1);

namespace Tests\Feature\Proposals;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Audit\Infrastructure\Persistence\ActionLog;
use App\Modules\Chat\Presentation\Tools\ProposePauseTool;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Experiments\Domain\Exceptions\ExperimentNotFoundException;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use App\Modules\Proposals\Application\Services\ProposalExecutionService;
use App\Modules\Proposals\Domain\Enums\ProposalOrigin;
use App\Modules\Proposals\Domain\Enums\ProposalStatus;
use App\Modules\Proposals\Domain\Exceptions\ProposalExecutionNotPermittedException;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The behavioural half of the approval invariant. ModuleBoundariesTest proves statically
 * that no chat tool so much as names ProposalExecutionService; this proves what actually
 * happens when one runs: a `Proposal` row appears, the experiment is untouched, and asking
 * the container for the execution service outside the accept controller throws.
 */
class ProposalCreationTest extends TestCase
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
        ]);
    }

    public function test_a_chat_tool_writes_a_pending_proposal(): void
    {
        $this->proposePause();

        $this->assertDatabaseHas('proposals', [
            'account_id' => $this->account->id,
            'experiment_id' => $this->experiment->id,
            'status' => ProposalStatus::Pending->value,
            'origin' => ProposalOrigin::Chat->value,
            'type' => 'pause_experiment',
        ]);
    }

    public function test_a_chat_tool_executes_nothing(): void
    {
        $this->proposePause();

        $this->assertSame('running', $this->experiment->refresh()->status->value);
        $this->assertNull($this->experiment->refresh()->verdict);
        $this->assertNull($this->experiment->refresh()->production_status);
        $this->assertSame(0, ActionLog::query()->where('action', 'proposal.accepted')->count());
    }

    public function test_a_chat_tool_may_not_propose_against_another_accounts_experiment(): void
    {
        $foreign = Experiment::factory()->create();

        $this->expectException(ExperimentNotFoundException::class);

        $this->app->make(ProposePauseTool::class)->handle(
            [
                'experiment_id' => (int) $foreign->id,
                'title' => 'Pausar el experimento de otra cuenta',
                'rationale' => 'No debería poder.',
            ],
            new AccountContext((int) $this->account->id, (int) $this->user->id),
        );
    }

    public function test_the_execution_service_cannot_be_resolved_outside_the_approval_door(): void
    {
        $this->expectException(ProposalExecutionNotPermittedException::class);

        $this->app->make(ProposalExecutionService::class);
    }

    private function proposePause(): void
    {
        $tool = $this->app->make(ProposePauseTool::class);

        $tool->handle(
            $tool->validate([
                'experiment_id' => (int) $this->experiment->id,
                'title' => 'Pausar el conjunto de anuncios',
                'rationale' => 'El CPA lleva cinco días por encima del objetivo.',
            ]),
            new AccountContext((int) $this->account->id, (int) $this->user->id),
        );
    }
}
