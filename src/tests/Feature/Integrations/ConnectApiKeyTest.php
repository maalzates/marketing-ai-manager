<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use App\Modules\Integrations\Domain\Enums\IntegrationStatus;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\EmulatesASecondRequest;
use Tests\Support\FakeTransport;
use Tests\TestCase;

/**
 * Storing a key is the moment the application takes custody of somebody's money. These
 * tests exist to prove the key goes in, stays encrypted, and never comes back out.
 */
class ConnectApiKeyTest extends TestCase
{
    use EmulatesASecondRequest, RefreshDatabase;

    private const string ANTHROPIC_KEY = 'sk-ant-api03-9TgqLmXeR4vZa8Kd2NpQwYb6HcJf0SuT1iOl3Mn5Bx7Ry9Ez';

    private const string ANTHROPIC_MODELS_BODY = '{"data":[{"type":"model","id":"claude-opus-5","display_name":"Claude Opus 5","created_at":"2026-05-14T00:00:00Z"}],"has_more":true,"first_id":"claude-opus-5","last_id":"claude-opus-5"}';

    private const string GEMINI_MODELS_BODY = '{"models":[{"name":"models/gemini-3.7-flash","baseModelId":"gemini-3.7-flash","version":"3.7","displayName":"Gemini 3.7 Flash","inputTokenLimit":1000000,"outputTokenLimit":4096,"supportedGenerationMethods":["generateContent"]}],"nextPageToken":"cGFnZV8y"}';

    private Account $account;

    private FakeTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = FakeTransport::silent()->install($this->app);
        $this->account = $this->actAsMemberOfANewAccount();
    }

    public function test_the_stored_key_never_appears_in_the_response(): void
    {
        $response = $this->connectAnthropic()->assertOk();

        $this->assertStringNotContainsString(self::ANTHROPIC_KEY, $response->getContent());
    }

    public function test_only_the_last_four_characters_of_the_key_are_reported_back(): void
    {
        $this->connectAnthropic()
            ->assertOk()
            ->assertJsonPath('result.masked_key', '****'.substr(self::ANTHROPIC_KEY, -4))
            ->assertJsonPath('result.status', IntegrationStatus::CONNECTED->value);
    }

    public function test_the_stored_key_never_appears_in_the_list_of_integrations(): void
    {
        $this->connectAnthropic()->assertOk();

        $response = $this->getJson('/api/v1/integrations')->assertOk();

        $this->assertStringNotContainsString(self::ANTHROPIC_KEY, $response->getContent());
        $this->assertSame('****'.substr(self::ANTHROPIC_KEY, -4), $this->anthropicRow($response->json('result'))['masked_key']);
    }

    public function test_every_provider_is_listed_even_when_nothing_is_connected(): void
    {
        $response = $this->getJson('/api/v1/integrations')->assertOk();

        $this->assertCount(count(IntegrationProvider::cases()), $response->json('result'));
        $this->assertSame(IntegrationStatus::DISCONNECTED->value, $this->anthropicRow($response->json('result'))['status']);
    }

    /**
     * Settings → Models offers a list instead of a text field, and the list comes from here:
     * the same response already says which providers are connected, so availability and
     * catalogue never have to be cross-referenced by the client.
     */
    public function test_every_llm_provider_carries_the_models_it_offers(): void
    {
        $response = $this->getJson('/api/v1/integrations')->assertOk();

        $anthropic = collect($this->anthropicRow($response->json('result'))['models']);

        $this->assertContains('claude-opus-5', $anthropic->pluck('id')->all());
        $this->assertSame(['id', 'input', 'output', 'state'], array_keys($anthropic->first()));
    }

    public function test_a_provider_that_is_not_an_llm_carries_no_models(): void
    {
        $response = $this->getJson('/api/v1/integrations')->assertOk();

        $apify = collect($response->json('result'))->firstWhere('provider', IntegrationProvider::APIFY->value);

        $this->assertSame([], $apify['models']);
    }

    /** JSON has no float, so the guarantee is "a number the client can compare", not a type. */
    public function test_the_catalogue_never_leaks_a_model_price_as_a_string(): void
    {
        $response = $this->getJson('/api/v1/integrations')->assertOk();

        $first = $this->anthropicRow($response->json('result'))['models'][0];

        $this->assertIsNumeric($first['input']);
        $this->assertIsNumeric($first['output']);
    }

    public function test_the_credentials_column_is_encrypted_at_rest(): void
    {
        $this->connectAnthropic()->assertOk();

        $stored = (string) DB::table('integrations')->where('account_id', $this->account->id)->value('credentials');

        $this->assertStringNotContainsString(self::ANTHROPIC_KEY, $stored);
        $this->assertSame(
            self::ANTHROPIC_KEY,
            Integration::query()->where('account_id', $this->account->id)->sole()->credentials['api_key'],
        );
    }

    public function test_a_key_with_the_wrong_prefix_is_rejected_before_any_request_leaves(): void
    {
        $this->putJson('/api/v1/integrations/anthropic', ['api_key' => 'sk-proj-thisIsAnOpenAiKey'])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.message', 'Anthropic rechazó las credenciales. Revísalas y vuelve a intentarlo.');

        $this->assertSame(0, $this->transport->requestCount());
    }

    public function test_a_gemini_key_with_the_newer_prefix_is_accepted_by_the_format_check(): void
    {
        $this->transport->queue(FakeTransport::json(self::GEMINI_MODELS_BODY));

        $this->putJson('/api/v1/integrations/gemini', ['api_key' => 'AQ.Ab8RN6JmZ1qXk4Tt0wPvLcHy2sDgUe7Rn9Fa3Bo5Vi1Xz'])
            ->assertOk()
            ->assertJsonPath('result.status', IntegrationStatus::CONNECTED->value);

        $this->assertSame(1, $this->transport->requestCount());
    }

    public function test_a_rejected_key_leaves_the_providers_body_in_the_log_context_and_out_of_the_response(): void
    {
        Log::spy();
        $this->transport->queue(FakeTransport::fixture('openai-401.json', Response::HTTP_UNAUTHORIZED));

        $response = $this->putJson('/api/v1/integrations/openai', ['api_key' => 'sk-proj-Zq4Rn8Vt1Xy6Kd0Lp3Mw7Sc2Hb5Ge9Ja'])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.message', 'OpenAI rechazó las credenciales. Revísalas y vuelve a intentarlo.');

        $this->assertStringNotContainsString('invalid_api_key', $response->getContent());

        Log::shouldHaveReceived('log')->withArgs(
            fn (string $level, string $message, array $context): bool => ($context['diagnosis']['body']['error']['code'] ?? null) === 'invalid_api_key'
        )->once();
    }

    public function test_a_provider_that_only_accepts_oauth_cannot_be_connected_with_a_key(): void
    {
        $this->putJson('/api/v1/integrations/google', ['api_key' => 'ya29.someAccessTokenPastedInTheWrongBox'])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.message', 'Google no se conecta con una API key.');

        $this->assertSame(0, $this->transport->requestCount());
        $this->assertDatabaseCount('integrations', 0);
    }

    public function test_a_key_shorter_than_the_minimum_is_rejected_by_validation(): void
    {
        $this->putJson('/api/v1/integrations/anthropic', ['api_key' => 'sk-ant'])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.fields.api_key.0', 'The api key field must be at least 8 characters.');
    }

    public function test_each_account_gets_a_client_carrying_its_own_key(): void
    {
        $other = Account::factory()->create();
        Integration::factory()->anthropic()->for($other)->create(['credentials' => ['api_key' => 'sk-ant-api03-OTHER-ACCOUNT-KEY-000000000000']]);
        Integration::factory()->anthropic()->for($this->account)->create(['credentials' => ['api_key' => self::ANTHROPIC_KEY]]);

        $this->transport->queue(
            FakeTransport::json(self::ANTHROPIC_MODELS_BODY),
            FakeTransport::json(self::ANTHROPIC_MODELS_BODY),
        );

        $this->postJson('/api/v1/integrations/anthropic/verify')->assertOk();

        $this->betweenRequests();
        $this->actAsMemberOf($other);
        $this->postJson('/api/v1/integrations/anthropic/verify')->assertOk();

        $this->assertSame(self::ANTHROPIC_KEY, $this->transport->header('x-api-key', 0));
        $this->assertSame('sk-ant-api03-OTHER-ACCOUNT-KEY-000000000000', $this->transport->header('x-api-key', 1));
    }

    public function test_another_accounts_key_is_never_listed(): void
    {
        $other = Account::factory()->create();
        Integration::factory()->anthropic()->for($other)->create(['credentials' => ['api_key' => self::ANTHROPIC_KEY]]);

        $response = $this->getJson('/api/v1/integrations')->assertOk();

        $this->assertStringNotContainsString(self::ANTHROPIC_KEY, $response->getContent());
        $this->assertNull($this->anthropicRow($response->json('result'))['masked_key']);
    }

    public function test_another_accounts_integration_cannot_be_disconnected(): void
    {
        $other = Account::factory()->create();
        $integration = Integration::factory()->anthropic()->for($other)->create();

        $this->deleteJson('/api/v1/integrations/anthropic')
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.message', 'No has conectado Anthropic todavía. Configúralo en Integraciones.');

        $this->assertDatabaseHas('integrations', ['id' => $integration->id]);
    }

    public function test_disconnecting_removes_the_callers_own_integration(): void
    {
        $integration = Integration::factory()->anthropic()->for($this->account)->create();

        $this->deleteJson('/api/v1/integrations/anthropic')->assertNoContent();

        $this->assertDatabaseMissing('integrations', ['id' => $integration->id]);
    }

    public function test_listing_integrations_requires_authentication(): void
    {
        app('auth')->forgetGuards();

        $this->getJson('/api/v1/integrations')->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    private function connectAnthropic(): TestResponse
    {
        $this->transport->queue(FakeTransport::json(self::ANTHROPIC_MODELS_BODY));

        return $this->putJson('/api/v1/integrations/anthropic', ['api_key' => self::ANTHROPIC_KEY]);
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function anthropicRow(array $rows): array
    {
        return collect($rows)->firstWhere('provider', IntegrationProvider::ANTHROPIC->value);
    }

    private function actAsMemberOfANewAccount(): Account
    {
        return $this->actAsMemberOf(Account::factory()->create());
    }

    private function actAsMemberOf(Account $account): Account
    {
        $user = User::factory()->create();
        $user->accounts()->attach($account);

        Sanctum::actingAs($user);

        return $account;
    }
}
