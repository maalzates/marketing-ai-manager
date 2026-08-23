<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Chat\Domain\Enums\MessageRole;
use App\Modules\Chat\Infrastructure\Persistence\ChatMessage;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;
use App\Modules\Settings\Infrastructure\Persistence\Setting;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use GuzzleHttp\Psr7\Response as ProviderResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\FakeTransport;
use Tests\TestCase;

/**
 * The loop: the model asks for a tool, the tool runs, its result goes back in, and the next
 * pass answers. Two things are worth proving beyond that it works — the result really
 * travels back to the provider, and the loop cannot run forever on the account's own key.
 */
class ChatToolLoopTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private FakeTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = FakeTransport::silent()->install($this->app);
        $this->actAsMemberOfANewAccount();

        Integration::factory()->anthropic()->for($this->account)->create();
    }

    public function test_executes_the_read_tool_the_model_asked_for(): void
    {
        $experiment = $this->runningExperimentOf($this->account, 'EXP-A01');
        $this->transport->queue(
            FakeTransport::fixture('anthropic-chat-tool-read.json'),
            FakeTransport::fixture('anthropic-text.json'),
        );

        $this->postJson('/api/v1/chat', ['message' => '¿Qué experimentos tengo activos?'])->assertOk();

        $result = ChatMessage::query()->where('role', MessageRole::Tool)->sole();

        $this->assertSame('get_experiments', $result->tool_name);
        $this->assertSame([(int) $experiment->id], array_column($result->tool_result, 'id'));
    }

    /** A tool whose result never reaches the provider is a tool the model cannot use. */
    public function test_feeds_the_tool_result_back_into_the_next_provider_call(): void
    {
        $this->runningExperimentOf($this->account, 'EXP-A01');
        $this->transport->queue(
            FakeTransport::fixture('anthropic-chat-tool-read.json'),
            FakeTransport::fixture('anthropic-text.json'),
        );

        $this->postJson('/api/v1/chat', ['message' => '¿Qué experimentos tengo activos?'])->assertOk();

        $this->assertSame(2, $this->transport->requestCount());
        $this->assertStringContainsString('toolu_read001', $this->transport->body(1));
        $this->assertStringContainsString('EXP-A01', $this->transport->body(1));
    }

    public function test_answers_with_the_reply_that_follows_the_tool_result(): void
    {
        $this->runningExperimentOf($this->account, 'EXP-A01');
        $this->transport->queue(
            FakeTransport::fixture('anthropic-chat-tool-read.json'),
            FakeTransport::fixture('anthropic-text.json'),
        );

        $this->postJson('/api/v1/chat', ['message' => '¿Qué experimentos tengo activos?'])
            ->assertOk()
            ->assertJsonPath('result.messages.4.content', 'The capital of France is Paris.');
    }

    /** On a BYOK key every extra round trip is the user's money, so the bound is a hard stop. */
    public function test_stops_asking_for_tools_once_the_round_trip_limit_is_reached(): void
    {
        $this->runningExperimentOf($this->account, 'EXP-A01');
        Setting::factory()->forAccount((int) $this->account->id)->create([
            'key' => 'chat.max_tool_round_trips',
            'value' => 2,
        ]);
        $this->transport->queue(...array_map(
            static fn (): ProviderResponse => FakeTransport::fixture('anthropic-chat-tool-read.json'),
            range(1, 3),
        ));

        $this->postJson('/api/v1/chat', ['message' => 'dame vueltas'])
            ->assertStatus(Response::HTTP_CONFLICT)
            ->assertJsonPath(
                'errors.message',
                'The assistant could not finish within its tool-use limit. Try a narrower question.',
            );

        $this->assertSame(3, $this->transport->requestCount());
    }

    public function test_a_tool_run_for_one_account_never_returns_another_accounts_rows(): void
    {
        $mine = $this->runningExperimentOf($this->account, 'EXP-MINE');
        $this->runningExperimentOf(Account::factory()->create(), 'EXP-THEIRS');
        $this->transport->queue(
            FakeTransport::fixture('anthropic-chat-tool-read.json'),
            FakeTransport::fixture('anthropic-text.json'),
        );

        $this->postJson('/api/v1/chat', ['message' => '¿Qué experimentos tengo activos?'])->assertOk();

        $result = ChatMessage::query()->where('role', MessageRole::Tool)->sole();

        $this->assertSame([(int) $mine->id], array_column($result->tool_result, 'id'));
    }

    /** A wrongly-called tool comes back as a result the model can correct, not as a dead turn. */
    public function test_an_invalid_tool_input_comes_back_to_the_model_as_an_error_result(): void
    {
        $this->transport->queue(
            FakeTransport::json($this->readToolBodyAskingFor(['status' => 'nonexistent-status'])),
            FakeTransport::fixture('anthropic-text.json'),
        );

        $this->postJson('/api/v1/chat', ['message' => '¿Qué experimentos tengo activos?'])->assertOk();

        $result = ChatMessage::query()->where('role', MessageRole::Tool)->sole();

        $this->assertArrayHasKey('error', $result->tool_result);
        $this->assertStringContainsString('"is_error":true', $this->transport->body(1));
    }

    /** @param array<string, mixed> $input */
    private function readToolBodyAskingFor(array $input): string
    {
        $body = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/llm/anthropic-chat-tool-read.json')),
            true,
        );
        $body['content'][1]['input'] = $input;

        return (string) json_encode($body);
    }

    private function runningExperimentOf(Account $account, string $code): Experiment
    {
        return Experiment::factory()->running()->create([
            'account_id' => $account->id,
            'strategy_id' => Strategy::factory()->create(['account_id' => $account->id])->id,
            'code' => $code,
        ]);
    }

    private function actAsMemberOfANewAccount(): void
    {
        $this->account = Account::factory()->create();
        $user = User::factory()->create();
        $user->accounts()->attach($this->account);

        Sanctum::actingAs($user);
    }
}
