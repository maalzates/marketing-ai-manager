<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Ai\Application\DTO\AiRequestDTO;
use App\Modules\Ai\Application\Services\AiService;
use App\Modules\Ai\Domain\Contracts\LlmClientFactoryInterface;
use App\Modules\Ai\Domain\Enums\AiTask;
use App\Modules\Ai\Domain\Enums\LlmProvider;
use App\Modules\Ai\Infrastructure\Clients\FakeLlmClient;
use App\Modules\Ai\Infrastructure\Clients\FakeLlmClientFactory;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Settings\Infrastructure\Persistence\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * One completion, end to end, with the provider's own recorded body replayed through the
 * real adapter. What is really under test is the ledger: these are the user's own keys,
 * and a token counted on the wrong side of the cache line bills them for it.
 */
class AiCompletionTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = $this->actAsMemberOfANewAccount();

        Route::middleware(['auth:sanctum', 'account'])->post(
            '/api/testing/ai/complete',
            fn (Request $request, AiService $ai, AccountContext $account) => response()->json([
                'result' => [
                    'text' => $ai->complete(new AiRequestDTO(
                        $account->accountId,
                        AiTask::Chat,
                        (string) $request->input('prompt'),
                        userId: $account->userId,
                    ))->text,
                ],
                'errors' => [],
            ]),
        );
    }

    public function test_a_completion_returns_the_text_the_model_answered(): void
    {
        $this->replaying('anthropic-text.json');

        $this->complete()
            ->assertOk()
            ->assertJsonPath('result.text', 'The capital of France is Paris.');
    }

    public function test_a_completion_writes_exactly_one_row_to_the_usage_ledger(): void
    {
        $this->replaying('anthropic-text.json');

        $this->complete()->assertOk();

        $this->assertDatabaseCount('llm_usage_logs', 1);
        $this->assertDatabaseHas('llm_usage_logs', [
            'account_id' => $this->account->id,
            'feature' => AiTask::Chat->value,
            'provider' => LlmProvider::Anthropic->value,
            'input_tokens' => 19,
            'output_tokens' => 9,
        ]);
    }

    /** Anthropic reports an input count that excludes cache reads, so nothing is subtracted. */
    public function test_anthropic_cache_reads_are_recorded_beside_the_uncached_input(): void
    {
        $this->replaying('anthropic-cached.json');

        $this->complete()->assertOk();

        $this->assertDatabaseHas('llm_usage_logs', [
            'account_id' => $this->account->id,
            'input_tokens' => 19,
            'cached_input_tokens' => 1500,
        ]);
    }

    /** OpenAI reports an input count that already contains the cache reads, so they come off. */
    public function test_openai_cache_reads_are_taken_out_of_the_reported_input(): void
    {
        $this->routeChatTo('gpt-5.6-luna');
        $this->replaying('openai-cached.json');

        $this->complete()->assertOk();

        $this->assertDatabaseHas('llm_usage_logs', [
            'account_id' => $this->account->id,
            'input_tokens' => 19,
            'cached_input_tokens' => 1500,
        ]);
    }

    public function test_hidden_reasoning_is_recorded_apart_from_the_visible_output(): void
    {
        $this->routeChatTo('gpt-5.6-sol');
        $this->replaying('openai-reasoning.json');

        $this->complete()->assertOk();

        $this->assertDatabaseHas('llm_usage_logs', [
            'account_id' => $this->account->id,
            'output_tokens' => 10,
            'reasoning_tokens' => 100,
        ]);
    }

    /** Cache reads are billed at a tenth of the input rate on every provider. */
    public function test_the_recorded_cost_prices_cache_reads_at_the_cheaper_rate(): void
    {
        $this->replaying('anthropic-cached.json');

        $this->complete()->assertOk();

        $this->assertDatabaseHas('llm_usage_logs', ['estimated_cost_usd' => '0.001070']);
    }

    public function test_the_usage_row_belongs_to_the_calling_account(): void
    {
        $other = Account::factory()->create();
        $this->replaying('anthropic-text.json');

        $this->complete()->assertOk();

        $this->assertDatabaseMissing('llm_usage_logs', ['account_id' => $other->id]);
    }

    public function test_a_provider_failure_reaches_the_caller_as_our_own_message(): void
    {
        Log::spy();
        $this->replaying('anthropic-500.json', Response::HTTP_INTERNAL_SERVER_ERROR);

        $response = $this->complete()
            ->assertStatus(Response::HTTP_BAD_GATEWAY)
            ->assertJsonPath('errors.message', 'The Anthropic API rejected the request. Check the key and the model in Settings, then try again.');

        $this->assertStringNotContainsString('api_error', $response->getContent());

        Log::shouldHaveReceived('log')->withArgs(
            fn (string $level, string $message, array $context): bool => ($context['response_body']['error']['type'] ?? null) === 'api_error'
        )->once();
    }

    public function test_a_failed_call_writes_nothing_to_the_usage_ledger(): void
    {
        $this->replaying('anthropic-500.json', Response::HTTP_INTERNAL_SERVER_ERROR);

        $this->complete()->assertStatus(Response::HTTP_BAD_GATEWAY);

        $this->assertDatabaseCount('llm_usage_logs', 0);
    }

    public function test_a_key_the_provider_refuses_is_reported_as_a_key_problem_not_a_session_one(): void
    {
        $this->replaying('anthropic-401.json', Response::HTTP_UNAUTHORIZED);

        $this->complete()
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.message', 'Your Anthropic API key was rejected. Reconnect it in Settings → Integrations.');
    }

    public function test_a_completion_requires_authentication(): void
    {
        app('auth')->forgetGuards();

        $this->postJson('/api/testing/ai/complete', ['prompt' => 'hola'])->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    private function complete(): TestResponse
    {
        return $this->postJson('/api/testing/ai/complete', ['prompt' => '¿Cuál es la capital de Francia?']);
    }

    private function replaying(string $fixture, int $status = 200): void
    {
        $this->app->instance(LlmClientFactoryInterface::class, new FakeLlmClientFactory(
            static fn (int $accountId, LlmProvider $provider) => FakeLlmClient::replaying($provider, $fixture, $status),
        ));
    }

    private function routeChatTo(string $model): void
    {
        Setting::factory()->forAccount($this->account->id)->create([
            'key' => 'ai.models.per_task.chat',
            'value' => $model,
        ]);
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
