<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Ai\Application\DTO\AiRequestDTO;
use App\Modules\Ai\Application\Services\AiService;
use App\Modules\Ai\Domain\Enums\AiTask;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;
use App\Modules\Knowledge\Infrastructure\Persistence\KnowledgeEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeTransport;
use Tests\TestCase;

/**
 * Prompt caching is worth roughly 90% of the input bill, and every provider matches its
 * cache on a byte-identical prefix. So the assertions here are about ordering and
 * stability of the head, not about the answer.
 */
class PromptAssemblyTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private FakeTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = FakeTransport::silent()->install($this->app);
        $this->account = $this->actAsMemberOfANewAccount();

        Integration::factory()->anthropic()->for($this->account)->create();

        Route::middleware(['auth:sanctum', 'account'])->post(
            '/api/testing/ai/prompt',
            fn (Request $request, AiService $ai, AccountContext $account) => response()->json([
                'result' => ['text' => $ai->complete(new AiRequestDTO(
                    $account->accountId,
                    AiTask::Chat,
                    (string) $request->input('prompt'),
                    tools: (array) $request->input('tools'),
                    userId: $account->userId,
                ))->text],
                'errors' => [],
            ]),
        );
    }

    public function test_the_system_prompt_carries_the_domain_knowledge(): void
    {
        KnowledgeEntry::factory()->create(['title' => 'Regla de presupuesto', 'body' => 'Nunca superar el tope mensual.']);
        $this->transport->queue(FakeTransport::fixture('anthropic-text.json'));

        $this->ask('¿Cuál es la capital de Francia?')->assertOk();

        $this->assertStringContainsString('Regla de presupuesto', $this->transport->decodedBody()['system'][0]['text']);
    }

    public function test_the_volatile_turn_stays_out_of_the_cached_head(): void
    {
        $this->transport->queue(FakeTransport::fixture('anthropic-text.json'));

        $this->ask('¿Cuál es la capital de Francia?')->assertOk();

        $body = $this->transport->decodedBody();
        $this->assertStringNotContainsString('capital de Francia', $body['system'][0]['text']);
        $this->assertSame('¿Cuál es la capital de Francia?', $body['messages'][0]['content']);
    }

    public function test_two_calls_with_different_turns_send_a_byte_identical_prefix(): void
    {
        KnowledgeEntry::factory()->create(['title' => 'Regla de presupuesto', 'body' => 'Nunca superar el tope mensual.']);
        $this->transport->queue(
            FakeTransport::fixture('anthropic-text.json'),
            FakeTransport::fixture('anthropic-text.json'),
        );

        $this->ask('Primera pregunta')->assertOk();
        $this->ask('Segunda pregunta, bastante más larga que la primera')->assertOk();

        $prefix = substr($this->transport->body(0), 0, strpos($this->transport->body(0), '"messages"'));

        $this->assertStringContainsString('Regla de presupuesto', $prefix);
        $this->assertStringStartsWith($prefix, $this->transport->body(1));
    }

    /** Unsorted tool definitions reorder between requests and break the cache silently. */
    public function test_tools_declared_in_a_different_order_are_sent_in_the_same_order(): void
    {
        $this->transport->queue(
            FakeTransport::fixture('anthropic-text.json'),
            FakeTransport::fixture('anthropic-text.json'),
        );

        $this->ask('hola', [$this->tool('zeta'), $this->tool('alfa')])->assertOk();
        $this->ask('hola', [$this->tool('alfa'), $this->tool('zeta')])->assertOk();

        $this->assertSame(
            $this->transport->decodedBody(0)['tools'],
            $this->transport->decodedBody(1)['tools'],
        );
    }

    private function ask(string $prompt, array $tools = []): TestResponse
    {
        return $this->postJson('/api/testing/ai/prompt', ['prompt' => $prompt, 'tools' => $tools]);
    }

    private function tool(string $name): array
    {
        return ['name' => $name, 'description' => "Hace {$name}.", 'schema' => ['type' => 'object', 'properties' => []]];
    }

    private function actAsMemberOfANewAccount(): Account
    {
        $account = Account::factory()->create();
        $user = User::factory()->create();
        $user->accounts()->attach($account);

        Sanctum::actingAs($user);

        return $account;
    }
}
