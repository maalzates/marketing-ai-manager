<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Ai\Application\DTO\AiRequestDTO;
use App\Modules\Ai\Application\DTO\Messages\TextMessage;
use App\Modules\Ai\Application\DTO\Messages\ToolCallMessage;
use App\Modules\Ai\Application\DTO\Messages\ToolResultMessage;
use App\Modules\Ai\Application\Services\AiService;
use App\Modules\Ai\Domain\Contracts\LlmMessageInterface;
use App\Modules\Ai\Domain\Enums\AiTask;
use App\Modules\Ai\Domain\Enums\LlmProvider;
use App\Modules\Ai\Domain\Enums\MessageRole;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Integrations\Domain\Enums\IntegrationStatus;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;
use App\Modules\Settings\Domain\Enums\SettingScope;
use App\Modules\Settings\Infrastructure\Persistence\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\FakeTransport;
use Tests\TestCase;

/**
 * One conversation — a user turn, the assistant's tool call, the tool's result — written
 * once in this application's own vocabulary and sent to all three providers.
 *
 * The assertions are on the outbound body because that is where this can go wrong
 * silently: an adapter that flattens a tool turn into prose returns no error, and the
 * model answers confidently from a JSON blob it was handed as if it were English.
 */
class ToolTurnTranslationTest extends TestCase
{
    use RefreshDatabase;

    private const string CALL_ID = 'toolu_abc123';

    private const string TOOL_NAME = 'get_weather';

    private const array TOOL_INPUT = ['location' => 'Boston, MA'];

    private const string TOOL_RESULT = '72F and sunny';

    private Account $account;

    private FakeTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = FakeTransport::silent()->install($this->app);
        $this->account = $this->actAsMemberOfANewAccount();

        $conversations = static fn (string $shape): array => match ($shape) {
            'tool_loop' => [
                new TextMessage(MessageRole::User, '¿Qué tiempo hace en Boston?'),
                new ToolCallMessage('Let me check the weather.', [
                    ['id' => self::CALL_ID, 'name' => self::TOOL_NAME, 'input' => self::TOOL_INPUT],
                ]),
                new ToolResultMessage([
                    ['id' => self::CALL_ID, 'name' => self::TOOL_NAME, 'content' => self::TOOL_RESULT],
                ]),
            ],
            'untranslatable' => [new UnsupportedMessage],
            default => [],
        };

        Route::middleware(['auth:sanctum', 'account'])->post(
            '/api/testing/ai/tool-turn',
            fn (Request $request, AiService $ai, AccountContext $account) => response()->json([
                'result' => [
                    'tool_calls' => $ai->complete(new AiRequestDTO(
                        $account->accountId,
                        AiTask::Chat,
                        '',
                        tools: [[
                            'name' => self::TOOL_NAME,
                            'description' => 'Get current weather for a location',
                            'schema' => ['type' => 'object', 'properties' => ['location' => ['type' => 'string']]],
                        ]],
                        history: $conversations((string) $request->input('shape')),
                        userId: $account->userId,
                    ))->toolCalls,
                ],
                'errors' => [],
            ]),
        );
    }

    public function test_anthropic_receives_the_tool_turn_as_content_blocks(): void
    {
        $this->drive(LlmProvider::Anthropic, 'anthropic-tool-use.json')->assertOk();

        $messages = $this->transport->decodedBody()['messages'];

        $this->assertSame(['role' => 'user', 'content' => '¿Qué tiempo hace en Boston?'], $messages[0]);
        $this->assertSame('assistant', $messages[1]['role']);
        $this->assertSame(['type' => 'text', 'text' => 'Let me check the weather.'], $messages[1]['content'][0]);
        $this->assertSame([
            'type' => 'tool_use',
            'id' => self::CALL_ID,
            'name' => self::TOOL_NAME,
            'input' => self::TOOL_INPUT,
        ], $messages[1]['content'][1]);
        $this->assertSame('user', $messages[2]['role']);
        $this->assertSame([
            'type' => 'tool_result',
            'tool_use_id' => self::CALL_ID,
            'content' => self::TOOL_RESULT,
        ], $messages[2]['content'][0]);
    }

    public function test_openai_receives_the_tool_turn_as_tool_calls_and_a_tool_role_message(): void
    {
        $this->drive(LlmProvider::OpenAi, 'openai-tool-calls.json')->assertOk();

        $messages = $this->transport->decodedBody()['messages'];

        $this->assertSame('system', $messages[0]['role']);
        $this->assertSame(['role' => 'user', 'content' => '¿Qué tiempo hace en Boston?'], $messages[1]);
        $this->assertSame('assistant', $messages[2]['role']);
        $this->assertSame([
            'id' => self::CALL_ID,
            'type' => 'function',
            'function' => ['name' => self::TOOL_NAME, 'arguments' => json_encode(self::TOOL_INPUT)],
        ], $messages[2]['tool_calls'][0]);
        $this->assertSame([
            'role' => 'tool',
            'tool_call_id' => self::CALL_ID,
            'content' => self::TOOL_RESULT,
        ], $messages[3]);
    }

    /** This provider addresses a result by function name, and has no tool role at all. */
    public function test_gemini_receives_the_tool_turn_as_function_call_and_function_response_parts(): void
    {
        $this->drive(LlmProvider::Gemini, 'gemini-tool-call.unverified.json')->assertOk();

        $contents = $this->transport->decodedBody()['contents'];

        $this->assertSame(['role' => 'user', 'parts' => [['text' => '¿Qué tiempo hace en Boston?']]], $contents[0]);
        $this->assertSame('model', $contents[1]['role']);
        $this->assertSame(['text' => 'Let me check the weather.'], $contents[1]['parts'][0]);
        $this->assertSame([
            'functionCall' => ['name' => self::TOOL_NAME, 'args' => self::TOOL_INPUT],
        ], $contents[1]['parts'][1]);
        $this->assertSame('user', $contents[2]['role']);
        $this->assertSame([
            'functionResponse' => [
                'name' => self::TOOL_NAME,
                'response' => ['result' => self::TOOL_RESULT],
            ],
        ], $contents[2]['parts'][0]);
    }

    public function test_a_turn_an_adapter_cannot_translate_is_refused_rather_than_approximated(): void
    {
        $this->connect(LlmProvider::Anthropic);

        $this->postJson('/api/testing/ai/tool-turn', ['shape' => 'untranslatable'])
            ->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR)
            ->assertJsonPath('errors.message', 'The Anthropic adapter has no faithful translation for a UnsupportedMessage turn.');

        $this->assertSame(0, $this->transport->requestCount());
    }

    public function test_the_openai_adapter_also_refuses_rather_than_approximating(): void
    {
        $this->connect(LlmProvider::OpenAi);
        $this->routeChatTo(LlmProvider::OpenAi);

        $this->postJson('/api/testing/ai/tool-turn', ['shape' => 'untranslatable'])
            ->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR)
            ->assertJsonPath('errors.message', 'The OpenAI adapter has no faithful translation for a UnsupportedMessage turn.');

        $this->assertSame(0, $this->transport->requestCount());
    }

    public function test_the_gemini_adapter_also_refuses_rather_than_approximating(): void
    {
        $this->connect(LlmProvider::Gemini);
        $this->routeChatTo(LlmProvider::Gemini);

        $this->postJson('/api/testing/ai/tool-turn', ['shape' => 'untranslatable'])
            ->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR)
            ->assertJsonPath('errors.message', 'The Gemini adapter has no faithful translation for a UnsupportedMessage turn.');

        $this->assertSame(0, $this->transport->requestCount());
    }

    /** An empty prompt means the history already ends with the turn to send. */
    public function test_a_history_ending_in_a_tool_result_gets_no_filler_user_turn_appended(): void
    {
        $this->drive(LlmProvider::Anthropic, 'anthropic-tool-use.json')->assertOk();

        $messages = $this->transport->decodedBody()['messages'];

        $this->assertCount(3, $messages);
        $this->assertSame('tool_result', $messages[2]['content'][0]['type']);
    }

    public function test_a_request_with_nothing_to_send_fails_as_our_own_error(): void
    {
        $integration = $this->connect(LlmProvider::Anthropic);

        $this->postJson('/api/testing/ai/tool-turn', ['shape' => 'empty'])
            ->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR)
            ->assertJsonPath('errors.message', 'An AI request was built with no messages to send.');

        $this->assertSame(0, $this->transport->requestCount());
        $this->assertSame(IntegrationStatus::CONNECTED, $integration->fresh()->status);
    }

    public function test_the_tool_call_the_model_answered_with_comes_back_ready_to_echo(): void
    {
        $this->drive(LlmProvider::Anthropic, 'anthropic-tool-use.json')
            ->assertOk()
            ->assertJsonPath('result.tool_calls.0.id', 'toolu_abc123')
            ->assertJsonPath('result.tool_calls.0.name', 'get_weather')
            ->assertJsonPath('result.tool_calls.0.input.location', 'Paris');
    }

    public function test_openai_tool_call_arguments_come_back_decoded(): void
    {
        $this->drive(LlmProvider::OpenAi, 'openai-tool-calls.json')
            ->assertOk()
            ->assertJsonPath('result.tool_calls.0.id', 'call_abc123')
            ->assertJsonPath('result.tool_calls.0.input.location', 'Boston, MA');
    }

    public function test_gemini_tool_calls_come_back_with_a_handle_the_loop_can_match_on(): void
    {
        $this->drive(LlmProvider::Gemini, 'gemini-tool-call.unverified.json')
            ->assertOk()
            ->assertJsonPath('result.tool_calls.0.id', 'call_0')
            ->assertJsonPath('result.tool_calls.0.name', 'get_weather')
            ->assertJsonPath('result.tool_calls.0.input.location', 'Boston, MA');
    }

    private function drive(LlmProvider $provider, string $fixture): TestResponse
    {
        $this->connect($provider);
        $this->routeChatTo($provider);
        $this->transport->queue(FakeTransport::fixture($fixture));

        return $this->postJson('/api/testing/ai/tool-turn', ['shape' => 'tool_loop']);
    }

    private const array MODELS = [
        'anthropic' => 'claude-sonnet-5',
        'openai' => 'gpt-5.6-luna',
        'gemini' => 'gemini-3.7-flash',
    ];

    private function connect(LlmProvider $provider): Integration
    {
        return Integration::factory()
            ->state(['provider' => $provider->value, 'credentials' => ['api_key' => "key-for-{$provider->value}"]])
            ->for($this->account)
            ->create();
    }

    private function routeChatTo(LlmProvider $provider): void
    {
        Setting::query()->updateOrCreate(
            ['scope' => SettingScope::ACCOUNT, 'scope_id' => $this->account->id, 'key' => 'ai.models.per_task.chat'],
            ['value' => self::MODELS[$provider->value]],
        );
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

/** A turn no adapter knows: the fallback that must not exist has to be reachable to test. */
readonly class UnsupportedMessage implements LlmMessageInterface {}
