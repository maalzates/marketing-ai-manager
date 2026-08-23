<?php

declare(strict_types=1);

namespace Tests\Feature\Proposals;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
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
 * A proposal is a pending mutation on someone else's platform account. Reading one is a
 * leak; accepting one is the leak plus the mutation, so both answer 404 rather than 403.
 */
class ProposalAccountIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const string NOT_FOUND_MESSAGE = 'Proposal not found.';

    private Proposal $foreign;

    private Experiment $foreignExperiment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-10 06:00:00'));

        $foreignAccount = Account::factory()->create();
        $this->foreignExperiment = Experiment::factory()->running()->create([
            'account_id' => $foreignAccount->id,
            'strategy_id' => Strategy::factory()->create(['account_id' => $foreignAccount->id]),
        ]);
        $this->foreign = Proposal::factory()->create([
            'account_id' => $foreignAccount->id,
            'experiment_id' => $this->foreignExperiment->id,
            'title' => 'De la cuenta B',
            'payload' => ['verdict' => Verdict::Worked->value, 'reason' => 'Ajena.'],
        ]);

        $user = User::factory()->create();
        $account = Account::factory()->create(['owner_user_id' => $user->id]);
        $user->accounts()->attach($account);

        Sanctum::actingAs($user);
    }

    public function test_does_not_list_another_accounts_proposals(): void
    {
        $this->getJson('/api/v1/proposals')
            ->assertOk()
            ->assertJsonPath('result.data', []);
    }

    public function test_does_not_read_another_accounts_proposal(): void
    {
        $this->getJson("/api/v1/proposals/{$this->foreign->id}")
            ->assertNotFound()
            ->assertJsonPath('errors.message', self::NOT_FOUND_MESSAGE);
    }

    public function test_does_not_accept_another_accounts_proposal(): void
    {
        $this->postJson("/api/v1/proposals/{$this->foreign->id}/accept")
            ->assertNotFound()
            ->assertJsonPath('errors.message', self::NOT_FOUND_MESSAGE);

        $this->assertDatabaseHas('proposals', [
            'id' => $this->foreign->id,
            'status' => ProposalStatus::Pending->value,
        ]);
        $this->assertNull($this->foreignExperiment->refresh()->verdict);
    }

    public function test_does_not_reject_another_accounts_proposal(): void
    {
        $this->postJson("/api/v1/proposals/{$this->foreign->id}/reject", ['reason' => 'No es mía.'])
            ->assertNotFound()
            ->assertJsonPath('errors.message', self::NOT_FOUND_MESSAGE);

        $this->assertDatabaseHas('proposals', [
            'id' => $this->foreign->id,
            'status' => ProposalStatus::Pending->value,
        ]);
    }
}
