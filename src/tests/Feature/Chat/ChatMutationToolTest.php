<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Campaigns\Infrastructure\Persistence\Campaign;
use App\Modules\Chat\Domain\Enums\MessageRole;
use App\Modules\Chat\Infrastructure\Persistence\ChatMessage;
use App\Modules\Experiments\Domain\Enums\ExperimentStatus;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;
use App\Modules\Proposals\Domain\Enums\ProposalOrigin;
use App\Modules\Proposals\Domain\Enums\ProposalStatus;
use App\Modules\Proposals\Domain\Enums\ProposalType;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeTransport;
use Tests\TestCase;

/**
 * The approval invariant, driven end to end. `ModuleBoundariesTest` already proves no tool
 * class can even name ProposalExecutionService; what matters here is the observable half —
 * the assistant asked to pause a live campaign, and nothing was paused.
 */
class ChatMutationToolTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private Experiment $experiment;

    private Campaign $campaign;

    private FakeTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = FakeTransport::silent()->install($this->app);
        $this->actAsMemberOfANewAccount();

        Integration::factory()->anthropic()->for($this->account)->create();
        Integration::factory()->meta()->for($this->account)->create();

        $this->experiment = Experiment::factory()->running()->create([
            'account_id' => $this->account->id,
            'strategy_id' => Strategy::factory()->create(['account_id' => $this->account->id])->id,
            'max_budget' => 500.00,
        ]);
        $this->campaign = Campaign::factory()->launched()->create([
            'account_id' => $this->account->id,
            'experiment_id' => $this->experiment->id,
        ]);
    }

    public function test_a_mutation_tool_writes_a_proposal_the_user_still_has_to_accept(): void
    {
        $this->askTheModelToPause();

        $this->assertDatabaseHas('proposals', [
            'account_id' => $this->account->id,
            'experiment_id' => $this->experiment->id,
            'type' => ProposalType::PauseExperiment->value,
            'origin' => ProposalOrigin::Chat->value,
            'status' => ProposalStatus::Pending->value,
        ]);
    }

    public function test_a_mutation_tool_leaves_the_experiment_exactly_as_it_found_it(): void
    {
        $this->askTheModelToPause();

        $this->experiment->refresh();

        $this->assertSame(ExperimentStatus::Running, $this->experiment->status);
        $this->assertSame('500.00', $this->experiment->max_budget);
        $this->assertFalse($this->experiment->closed_early);
    }

    public function test_a_mutation_tool_leaves_the_live_campaign_running_on_the_platform(): void
    {
        $statusBefore = $this->campaign->status;

        $this->askTheModelToPause();

        $this->campaign->refresh();

        $this->assertSame($statusBefore, $this->campaign->status);
        $this->assertDatabaseMissing('action_logs', ['action' => 'campaign.paused']);
    }

    /** Two calls left the machine, both to the model. None of them went to an ads platform. */
    public function test_a_mutation_tool_makes_no_provider_call_at_all(): void
    {
        $this->askTheModelToPause();

        $this->assertSame(2, $this->transport->requestCount());
        $this->assertSame('api.anthropic.com', $this->transport->request(0)->getUri()->getHost());
        $this->assertSame('api.anthropic.com', $this->transport->request(1)->getUri()->getHost());
    }

    public function test_the_proposal_travels_back_to_the_model_as_the_tool_result(): void
    {
        $this->askTheModelToPause();

        $result = ChatMessage::query()->where('role', MessageRole::Tool)->sole();

        $this->assertSame('propose_pause', $result->tool_name);
        $this->assertSame(ProposalStatus::Pending->value, $result->tool_result['status']);
    }

    private function askTheModelToPause(): void
    {
        $this->transport->queue(
            FakeTransport::json($this->pauseToolBodyFor((int) $this->experiment->id)),
            FakeTransport::fixture('anthropic-text.json'),
        );

        $this->postJson('/api/v1/chat', ['message' => 'Pausa el experimento, va fatal.'])->assertOk();
    }

    /** The fixture holds the shape; only the id has to name the row this test created. */
    private function pauseToolBodyFor(int $experimentId): string
    {
        $body = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/llm/anthropic-chat-tool-propose-pause.json')),
            true,
        );
        $body['content'][0]['input']['experiment_id'] = $experimentId;

        return (string) json_encode($body);
    }

    private function actAsMemberOfANewAccount(): void
    {
        $this->account = Account::factory()->create();
        $user = User::factory()->create();
        $user->accounts()->attach($this->account);

        Sanctum::actingAs($user);
    }
}
