<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Chat\Domain\Enums\MessageRole;
use App\Modules\Chat\Infrastructure\Persistence\ChatConversation;
use App\Modules\Chat\Infrastructure\Persistence\ChatMessage;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;
use App\Modules\Knowledge\Infrastructure\Persistence\KnowledgeEntry;
use App\Modules\Settings\Infrastructure\Persistence\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeTransport;
use Tests\TestCase;

/**
 * A long conversation must stop growing its own cost. Two mechanisms do that, and both are
 * only observable in what leaves the machine: the window drops old turns from the request,
 * and the head those turns are replaced by is byte-identical between calls so the provider
 * can charge the cached rate for it.
 */
class ChatHistoryWindowTest extends TestCase
{
    use RefreshDatabase;

    private const int WINDOW = 4;

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

    public function test_the_request_stops_carrying_the_turns_that_fell_out_of_the_window(): void
    {
        $conversation = $this->conversationOf(6);
        $this->narrowTheWindow();
        $this->transport->queue(
            FakeTransport::fixture('anthropic-text.json'),
            FakeTransport::fixture('anthropic-text.json'),
        );

        $this->postJson('/api/v1/chat', ['conversation_id' => $conversation->id, 'message' => 'sigue'])
            ->assertOk();

        $this->assertStringNotContainsString('MENSAJE-1', $this->transport->body(0));
        $this->assertStringContainsString('MENSAJE-6', $this->transport->body(0));
    }

    public function test_the_turns_that_fell_out_are_compacted_into_the_conversation_summary(): void
    {
        $conversation = $this->conversationOf(6);
        $this->narrowTheWindow();
        $this->transport->queue(
            FakeTransport::fixture('anthropic-text.json'),
            FakeTransport::fixture('anthropic-text.json'),
        );

        $this->postJson('/api/v1/chat', ['conversation_id' => $conversation->id, 'message' => 'sigue'])
            ->assertOk();

        $this->assertSame('The capital of France is Paris.', $conversation->refresh()->summary);
    }

    /** The database keeps every turn; only the prompt forgets them. */
    public function test_the_dropped_turns_are_still_readable_in_the_conversation(): void
    {
        $conversation = $this->conversationOf(6);
        $this->narrowTheWindow();
        $this->transport->queue(
            FakeTransport::fixture('anthropic-text.json'),
            FakeTransport::fixture('anthropic-text.json'),
        );

        $this->postJson('/api/v1/chat', ['conversation_id' => $conversation->id, 'message' => 'sigue'])
            ->assertOk();

        $this->assertDatabaseHas('chat_messages', [
            'chat_conversation_id' => $conversation->id,
            'content' => 'MENSAJE-1',
        ]);
    }

    public function test_the_summary_replaces_the_dropped_turns_in_the_next_request(): void
    {
        $conversation = $this->conversationOf(6);
        $this->narrowTheWindow();
        $this->transport->queue(...array_map(
            static fn (): mixed => FakeTransport::fixture('anthropic-text.json'),
            range(1, 4),
        ));

        $this->postJson('/api/v1/chat', ['conversation_id' => $conversation->id, 'message' => 'sigue'])
            ->assertOk();
        $this->postJson('/api/v1/chat', ['conversation_id' => $conversation->id, 'message' => 'y otra vez'])
            ->assertOk();

        $this->assertStringContainsString(
            'conversation_summary',
            $this->transport->decodedBody(2)['system'][0]['text'],
        );
    }

    /** Provider caching matches on bytes; a head that drifts costs roughly 90% of the input bill. */
    public function test_two_turns_of_one_conversation_send_a_byte_identical_prefix(): void
    {
        KnowledgeEntry::factory()->create(['title' => 'Regla de presupuesto', 'body' => 'Nunca superar el tope.']);
        $conversation = $this->conversationOf(0);
        $this->transport->queue(
            FakeTransport::fixture('anthropic-text.json'),
            FakeTransport::fixture('anthropic-text.json'),
        );

        $this->postJson('/api/v1/chat', ['conversation_id' => $conversation->id, 'message' => 'Primera'])
            ->assertOk();
        $this->postJson('/api/v1/chat', [
            'conversation_id' => $conversation->id,
            'message' => 'Segunda pregunta, bastante más larga que la primera',
        ])->assertOk();

        $prefix = substr($this->transport->body(0), 0, strpos($this->transport->body(0), '"messages"'));

        $this->assertStringContainsString('Regla de presupuesto', $prefix);
        $this->assertStringStartsWith($prefix, $this->transport->body(1));
    }

    private function narrowTheWindow(): void
    {
        Setting::factory()->forAccount((int) $this->account->id)->create([
            'key' => 'chat.history_window_messages',
            'value' => self::WINDOW,
        ]);
    }

    private function conversationOf(int $messages): ChatConversation
    {
        $conversation = ChatConversation::factory()
            ->create(['account_id' => $this->account->id, 'user_id' => $this->user->id]);

        foreach (range(1, $messages) as $index) {
            ChatMessage::factory()->create([
                'chat_conversation_id' => $conversation->id,
                'account_id' => $this->account->id,
                'role' => $index % 2 === 1 ? MessageRole::User : MessageRole::Assistant,
                'content' => "MENSAJE-{$index}",
            ]);
        }

        return $conversation;
    }

    private function actAsMemberOfANewAccount(): void
    {
        $this->account = Account::factory()->create();
        $this->user = User::factory()->create();
        $this->user->accounts()->attach($this->account);

        Sanctum::actingAs($this->user);
    }
}
