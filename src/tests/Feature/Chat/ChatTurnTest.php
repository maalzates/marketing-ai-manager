<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Chat\Domain\Enums\MessageRole;
use App\Modules\Chat\Infrastructure\Persistence\ChatConversation;
use App\Modules\Chat\Infrastructure\Persistence\ChatMessage;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;
use App\Modules\Settings\Domain\Enums\SettingScope;
use App\Modules\Settings\Infrastructure\Persistence\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\FakeTransport;
use Tests\TestCase;

/**
 * The one door onto the assistant. A turn is only finished when the reply is on screen and
 * both sides of it are rows: the conversation has to survive the request that produced it.
 */
class ChatTurnTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private User $user;

    private FakeTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = FakeTransport::silent()->install($this->app);
        $this->actAsMemberOfANewAccount();

        Integration::factory()->anthropic()->for($this->account)->create();
    }

    public function test_answers_with_the_assistants_reply(): void
    {
        $this->transport->queue(FakeTransport::fixture('anthropic-text.json'));

        $this->postJson('/api/v1/chat', ['message' => '¿Cuál es la capital de Francia?'])
            ->assertOk()
            ->assertJsonPath('result.messages.1.role', MessageRole::Assistant->value)
            ->assertJsonPath('result.messages.1.content', 'The capital of France is Paris.');
    }

    public function test_persists_the_user_turn_of_the_conversation(): void
    {
        $this->transport->queue(FakeTransport::fixture('anthropic-text.json'));

        $this->postJson('/api/v1/chat', ['message' => '¿Cuál es la capital de Francia?'])->assertOk();

        $this->assertDatabaseHas('chat_messages', [
            'account_id' => $this->account->id,
            'role' => MessageRole::User->value,
            'content' => '¿Cuál es la capital de Francia?',
        ]);
    }

    public function test_persists_the_assistant_turn_of_the_conversation(): void
    {
        $this->transport->queue(FakeTransport::fixture('anthropic-text.json'));

        $this->postJson('/api/v1/chat', ['message' => 'hola'])->assertOk();

        $this->assertDatabaseHas('chat_messages', [
            'account_id' => $this->account->id,
            'role' => MessageRole::Assistant->value,
            'content' => 'The capital of France is Paris.',
        ]);
    }

    /** The conversation's own token sum is what the usage screen adds up per chat. */
    public function test_records_the_token_counts_the_provider_billed_on_the_assistant_turn(): void
    {
        $this->transport->queue(FakeTransport::fixture('anthropic-text.json'));

        $this->postJson('/api/v1/chat', ['message' => 'hola'])->assertOk();

        $assistant = ChatMessage::query()->where('role', MessageRole::Assistant)->sole();

        $this->assertSame(19, $assistant->input_tokens);
        $this->assertSame(9, $assistant->output_tokens);
    }

    public function test_starts_a_conversation_titled_after_the_first_message(): void
    {
        $this->transport->queue(FakeTransport::fixture('anthropic-text.json'));

        $this->postJson('/api/v1/chat', ['message' => 'Quiero revisar el presupuesto'])->assertOk();

        $this->assertSame(
            'Quiero revisar el presupuesto',
            ChatConversation::query()->sole()->title,
        );
    }

    public function test_continues_an_existing_conversation_instead_of_opening_a_second_one(): void
    {
        $conversation = ChatConversation::factory()
            ->create(['account_id' => $this->account->id, 'user_id' => $this->user->id]);
        $this->transport->queue(FakeTransport::fixture('anthropic-text.json'));

        $this->postJson('/api/v1/chat', ['conversation_id' => $conversation->id, 'message' => 'seguimos'])
            ->assertOk()
            ->assertJsonPath('result.id', $conversation->id);

        $this->assertSame(1, ChatConversation::query()->count());
    }

    public function test_rejects_a_conversation_belonging_to_another_user(): void
    {
        $conversation = ChatConversation::factory()->create(['account_id' => $this->account->id]);

        $this->postJson('/api/v1/chat', ['conversation_id' => $conversation->id, 'message' => 'hola'])
            ->assertNotFound();

        $this->assertSame(0, $this->transport->requestCount());
    }

    public function test_rejects_a_message_that_is_missing(): void
    {
        $this->postJson('/api/v1/chat', [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.fields.message.0', 'The message field is required.');
    }

    /** A flag that only hides the button still bills the account, so it refuses before the call. */
    public function test_refuses_before_any_provider_call_when_the_chat_feature_is_off(): void
    {
        Setting::factory()->forAccount((int) $this->account->id)->create([
            'key' => 'features.chat',
            'value' => false,
        ]);

        $this->postJson('/api/v1/chat', ['message' => 'hola'])->assertForbidden();

        $this->assertSame(0, $this->transport->requestCount());
        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_refuses_before_any_provider_call_when_the_token_budget_is_exhausted(): void
    {
        Setting::factory()->create([
            'scope' => SettingScope::ACCOUNT,
            'scope_id' => $this->account->id,
            'key' => 'ai.budget.daily_tokens',
            'value' => 1,
        ]);

        $this->postJson('/api/v1/chat', ['message' => 'hola'])
            ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);

        $this->assertSame(0, $this->transport->requestCount());
    }

    private function actAsMemberOfANewAccount(): void
    {
        $this->account = Account::factory()->create();
        $this->user = User::factory()->create();
        $this->user->accounts()->attach($this->account);

        Sanctum::actingAs($this->user);
    }
}
