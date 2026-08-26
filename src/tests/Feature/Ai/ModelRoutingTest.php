<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Ai\Domain\Enums\AiTask;
use App\Modules\Ai\Domain\Enums\LlmProvider;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;
use App\Modules\Settings\Infrastructure\Persistence\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\EmulatesASecondRequest;
use Tests\Support\FakeTransport;
use Tests\TestCase;

/**
 * Which model answers which task, read off the request that actually left the machine.
 *
 * The transport is the real one here rather than a fake LLM client: the account's own key
 * is resolved, the provider is chosen from the model id, and both are visible only in the
 * outbound request.
 */
class ModelRoutingTest extends TestCase
{
    use EmulatesASecondRequest, RefreshDatabase;

    private const string ANTHROPIC_KEY = 'sk-ant-api03-9TgqLmXeR4vZa8Kd2NpQwYb6HcJf0SuT1iOl3Mn5Bx7Ry9Ez';

    private const string OPENAI_KEY = 'sk-proj-Zq4Rn8Vt1Xy6Kd0Lp3Mw7Sc2Hb5Ge9JaKx1';

    private Account $account;

    private FakeTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = FakeTransport::silent()->install($this->app);
        $this->account = $this->actAsMemberOf(Account::factory()->create());

        Integration::factory()->anthropic()->for($this->account)->create(['credentials' => ['api_key' => self::ANTHROPIC_KEY]]);
    }

    public function test_a_task_is_answered_by_the_model_configured_for_it(): void
    {
        $this->transport->queue(FakeTransport::fixture('anthropic-structured-suggestion.json'));

        $this->suggest(AiTask::FieldSuggestion)->assertOk();

        $this->assertSame('claude-haiku-4-5', $this->transport->decodedBody()['model']);
    }

    public function test_another_task_is_answered_by_its_own_model(): void
    {
        $this->transport->queue(FakeTransport::fixture('anthropic-structured-suggestion.json'));

        $this->suggest(AiTask::CampaignProposal)->assertOk();

        $this->assertSame('claude-sonnet-5', $this->transport->decodedBody()['model']);
    }

    public function test_one_model_for_everything_sends_every_task_to_the_chat_model(): void
    {
        $this->setting('ai.models.same_for_all', true);
        $this->transport->queue(FakeTransport::fixture('anthropic-structured-suggestion.json'));

        $this->suggest(AiTask::FieldSuggestion)->assertOk();

        $this->assertSame('claude-sonnet-5', $this->transport->decodedBody()['model']);
    }

    public function test_the_provider_is_chosen_from_the_model_and_paid_for_with_that_providers_key(): void
    {
        Integration::factory()->openAi()->for($this->account)->create(['credentials' => ['api_key' => self::OPENAI_KEY]]);
        $this->setting('ai.models.per_task.field_suggestion', 'gpt-5.6-luna');
        $this->transport->queue(FakeTransport::fixture('openai-structured-suggestion.json'));

        $this->suggest(AiTask::FieldSuggestion)->assertOk();

        $this->assertSame('/v1/chat/completions', $this->transport->path());
        $this->assertSame('Bearer '.self::OPENAI_KEY, $this->transport->header('Authorization'));
    }

    /**
     * A task added without its default in config/settings.php would route to nothing. The
     * registry is the only thing standing between that and a silent fallback, so every
     * declared task is driven through the endpoint rather than read out of config.
     */
    public function test_every_declared_task_resolves_to_a_model_this_application_can_call(): void
    {
        foreach (AiTask::cases() as $task) {
            $this->transport->queue(FakeTransport::fixture('anthropic-structured-suggestion.json'));

            $this->suggest($task)->assertOk();
        }

        $this->assertSame(count(AiTask::cases()), $this->transport->requestCount());
    }

    public function test_a_model_this_application_cannot_call_is_refused_before_any_provider_is_called(): void
    {
        $this->setting('ai.models.per_task.field_suggestion', 'llama-99-turbo');

        $this->suggest(AiTask::FieldSuggestion)
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.message', 'The model "llama-99-turbo" is not one this application knows how to call. Pick another in Settings → Models.');

        $this->assertSame(0, $this->transport->requestCount());
    }

    /**
     * The routing used to hand this straight to the credential resolver, which answered
     * "you have not connected OpenAI yet" — naming a provider the account never chose. The
     * account connected Anthropic, so the same tier on Anthropic is what it gets.
     */
    public function test_a_model_whose_provider_is_not_connected_falls_back_to_one_that_is(): void
    {
        $this->setting('ai.models.per_task.field_suggestion', 'gpt-5.6-luna');
        $this->transport->queue(FakeTransport::fixture('anthropic-structured-suggestion.json'));

        $this->suggest(AiTask::FieldSuggestion)->assertOk();

        $this->assertSame(
            LlmProvider::Anthropic->cheapestModel(),
            $this->transport->decodedBody()['model'],
        );
    }

    /** A judgement task keeps a capable model after the swap; only the provider changes. */
    public function test_the_capable_tier_survives_the_fallback(): void
    {
        $this->setting('ai.models.per_task.chat', 'gpt-5.6-sol');
        $this->transport->queue(FakeTransport::fixture('anthropic-structured-suggestion.json'));

        $this->suggest(AiTask::Chat)->assertOk();

        $this->assertSame(
            LlmProvider::Anthropic->capableModel(),
            $this->transport->decodedBody()['model'],
        );
    }

    /** With nothing to fall back to, the credential error is the honest answer. */
    public function test_a_provider_the_account_has_not_connected_is_reported_as_not_connected(): void
    {
        Integration::query()->where('account_id', $this->account->id)->delete();
        $this->setting('ai.models.per_task.field_suggestion', 'gpt-5.6-luna');

        $this->suggest(AiTask::FieldSuggestion)
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.message', 'No has conectado OpenAI todavía. Configúralo en Integraciones.');

        $this->assertSame(0, $this->transport->requestCount());
    }

    /**
     * Providers resolve an alias to a dated snapshot, and that id is not in the catalogue.
     * Pricing by the answer's model turned every aliased model into a dead end: the call
     * succeeded at the provider and the application then refused to bill it.
     */
    public function test_an_answer_naming_a_dated_snapshot_is_still_priced_and_recorded(): void
    {
        $answer = json_decode((string) file_get_contents(base_path('tests/Fixtures/llm/anthropic-structured-suggestion.json')), true);
        $answer['model'] = 'claude-opus-5-20260514';
        $this->transport->queue(FakeTransport::json($answer));

        $this->suggest(AiTask::Chat)->assertOk();

        $this->assertDatabaseHas('llm_usage_logs', [
            'account_id' => $this->account->id,
            'model' => 'claude-opus-5-20260514',
        ]);
        $this->assertGreaterThan(0.0, (float) DB::table('llm_usage_logs')->value('estimated_cost_usd'));
    }

    public function test_each_account_calls_the_provider_with_its_own_key(): void
    {
        $other = Account::factory()->create();
        Integration::factory()->anthropic()->for($other)->create(['credentials' => ['api_key' => 'sk-ant-api03-OTHER-ACCOUNT-KEY-000000000000']]);
        $this->transport->queue(
            FakeTransport::fixture('anthropic-structured-suggestion.json'),
            FakeTransport::fixture('anthropic-structured-suggestion.json'),
        );

        $this->suggest(AiTask::FieldSuggestion)->assertOk();

        $this->betweenRequests();
        $this->actAsMemberOf($other);
        $this->suggest(AiTask::FieldSuggestion)->assertOk();

        $this->assertSame(self::ANTHROPIC_KEY, $this->transport->header('x-api-key', 0));
        $this->assertSame('sk-ant-api03-OTHER-ACCOUNT-KEY-000000000000', $this->transport->header('x-api-key', 1));
    }

    private function suggest(AiTask $task): TestResponse
    {
        return $this->postJson('/api/v1/ai/suggest', [
            'task' => $task->value,
            'target' => 'objetivo de la estrategia',
        ]);
    }

    private function setting(string $key, mixed $value): void
    {
        Setting::factory()->forAccount($this->account->id)->create(['key' => $key, 'value' => $value]);
    }

    private function actAsMemberOf(Account $account): Account
    {
        $user = User::factory()->create();
        $user->accounts()->attach($account);

        Sanctum::actingAs($user);

        return $account;
    }
}
