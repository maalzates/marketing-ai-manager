<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Integrations\Domain\Enums\IntegrationStatus;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\FakeTransport;
use Tests\TestCase;

/**
 * One verification per provider, each replaying the body its own documentation prints.
 *
 * The failure cases are the reason this file is long: no two providers signal a bad key
 * the same way, and telling somebody their working key is broken — or storing a dead one
 * as connected — are both worse than not checking at all.
 */
class IntegrationVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const string ANTHROPIC_MODELS_BODY = '{"data":[{"type":"model","id":"claude-opus-5","display_name":"Claude Opus 5","created_at":"2026-05-14T00:00:00Z"}],"has_more":true,"first_id":"claude-opus-5","last_id":"claude-opus-5"}';

    private const string OPENAI_MODELS_BODY = '{"object":"list","data":[{"id":"gpt-5.6-luna","object":"model","created":1686935002,"owned_by":"openai","shutdown_date":null}]}';

    private const string GEMINI_MODELS_BODY = '{"models":[{"name":"models/gemini-3.7-flash","baseModelId":"gemini-3.7-flash","version":"3.7","displayName":"Gemini 3.7 Flash","inputTokenLimit":1000000,"outputTokenLimit":4096,"supportedGenerationMethods":["generateContent"]}],"nextPageToken":"cGFnZV8y"}';

    private const string APIFY_USER_BODY = '{"data":{"id":"YiKoxjkaS9gjGTqhF","username":"myusername","email":"bob@example.com"}}';

    private const string META_ME_BODY = '{"name":"Jane Doe","id":"10223999342134"}';

    private const string META_DEAD_TOKEN_BODY = '{"error":{"message":"Error validating access token: Session has expired on Saturday, 22-Aug-26 11:00:00 PDT.","type":"OAuthException","code":190,"error_subcode":463,"fbtrace_id":"AsW9kL2mQ7xVn4pRt1YbCzD"}}';

    private const string CHECKED_AT = '2026-08-23T09:00:00+00:00';

    private Account $account;

    private FakeTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = FakeTransport::silent()->install($this->app);
        $this->account = $this->actAsMemberOfANewAccount();

        $this->travelTo(self::CHECKED_AT);
    }

    public function test_anthropic_is_verified_against_its_cheapest_models_call(): void
    {
        Integration::factory()->anthropic()->for($this->account)->disconnected()->create();
        $this->transport->queue(FakeTransport::json(self::ANTHROPIC_MODELS_BODY));

        $this->assertVerifiesAsConnected('anthropic');
        $this->assertSame('/v1/models', $this->transport->path());
        $this->assertSame('limit=1', $this->transport->query());
    }

    public function test_openai_is_verified_against_its_cheapest_models_call(): void
    {
        Integration::factory()->openAi()->for($this->account)->disconnected()->create();
        $this->transport->queue(FakeTransport::json(self::OPENAI_MODELS_BODY));

        $this->assertVerifiesAsConnected('openai');
        $this->assertSame('/v1/models', $this->transport->path());
    }

    public function test_gemini_is_verified_against_its_cheapest_models_call(): void
    {
        Integration::factory()->gemini()->for($this->account)->disconnected()->create();
        $this->transport->queue(FakeTransport::json(self::GEMINI_MODELS_BODY));

        $this->assertVerifiesAsConnected('gemini');
        $this->assertSame('/v1beta/models', $this->transport->path());
        $this->assertSame('pageSize=1', $this->transport->query());
    }

    public function test_apify_is_verified_against_the_current_user_endpoint(): void
    {
        Integration::factory()->apify()->for($this->account)->disconnected()->create();
        $this->transport->queue(FakeTransport::json(self::APIFY_USER_BODY));

        $this->assertVerifiesAsConnected('apify');
        $this->assertSame('/v2/users/me', $this->transport->path());
    }

    public function test_a_verified_apify_token_records_the_account_it_belongs_to(): void
    {
        Integration::factory()->apify()->for($this->account)->disconnected()->create();
        $this->transport->queue(FakeTransport::json(self::APIFY_USER_BODY));

        $this->postJson('/api/v1/integrations/apify/verify')
            ->assertOk()
            ->assertJsonPath('result.external_account_id', 'YiKoxjkaS9gjGTqhF');
    }

    public function test_meta_is_verified_against_the_versioned_me_endpoint(): void
    {
        Integration::factory()->meta()->for($this->account)->create(['status' => IntegrationStatus::DISCONNECTED]);
        $this->transport->queue(FakeTransport::json(self::META_ME_BODY));

        $this->assertVerifiesAsConnected('meta');
        $this->assertSame('/'.config('services.meta.graph_version').'/me', $this->transport->path());
    }

    public function test_anthropic_rejects_a_bad_key_with_a_401_and_the_row_is_marked_in_error(): void
    {
        $this->transport->queue(FakeTransport::fixture('anthropic-401.json', Response::HTTP_UNAUTHORIZED));

        $this->putJson('/api/v1/integrations/anthropic', ['api_key' => 'sk-ant-api03-Yn4Rq8Vt1Xy6Kd0Lp3Mw7Sc2Hb5Ge9Ja'])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.message', 'Anthropic rechazó las credenciales. Revísalas y vuelve a intentarlo.');

        $this->assertStoredStatus('anthropic', IntegrationStatus::ERROR);
    }

    public function test_openai_rejects_a_bad_key_with_a_401_and_the_row_is_marked_in_error(): void
    {
        $this->transport->queue(FakeTransport::fixture('openai-401.json', Response::HTTP_UNAUTHORIZED));

        $this->putJson('/api/v1/integrations/openai', ['api_key' => 'sk-proj-Zq4Rn8Vt1Xy6Kd0Lp3Mw7Sc2Hb5Ge9Ja'])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.message', 'OpenAI rechazó las credenciales. Revísalas y vuelve a intentarlo.');

        $this->assertStoredStatus('openai', IntegrationStatus::ERROR);
    }

    /** Gemini answers a bad key with 400, so only `error.details[].reason` tells the truth. */
    public function test_gemini_rejects_a_bad_key_with_a_400_and_the_row_is_marked_in_error(): void
    {
        $this->transport->queue(FakeTransport::fixture('gemini-400-invalid-key.json', Response::HTTP_BAD_REQUEST));

        $this->putJson('/api/v1/integrations/gemini', ['api_key' => 'AIzaSyBn4Rq8Vt1Xy6Kd0Lp3Mw7Sc2Hb5Ge9Jaz'])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.message', 'Gemini rechazó las credenciales. Revísalas y vuelve a intentarlo.');

        $this->assertStoredStatus('gemini', IntegrationStatus::ERROR);
    }

    public function test_a_grant_meta_itself_refused_is_marked_expired_rather_than_in_error(): void
    {
        Integration::factory()->meta()->for($this->account)->create();
        $this->transport->queue(FakeTransport::json(self::META_DEAD_TOKEN_BODY, Response::HTTP_BAD_REQUEST));

        $this->postJson('/api/v1/integrations/meta/verify')
            ->assertOk()
            ->assertJsonPath('result.status', IntegrationStatus::EXPIRED->value);
    }

    public function test_a_provider_that_did_not_answer_leaves_the_stored_status_alone(): void
    {
        Integration::factory()->anthropic()->for($this->account)->create();
        $this->transport->queue(FakeTransport::fixture('anthropic-500.json', Response::HTTP_INTERNAL_SERVER_ERROR));

        $this->postJson('/api/v1/integrations/anthropic/verify')
            ->assertOk()
            ->assertJsonPath('result.status', IntegrationStatus::CONNECTED->value)
            ->assertJsonPath('result.failure_count', 1);
    }

    public function test_verifying_an_integration_of_another_account_never_reaches_the_provider(): void
    {
        Integration::factory()->anthropic()->for(Account::factory()->create())->create();

        $this->postJson('/api/v1/integrations/anthropic/verify')
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.message', 'No has conectado Anthropic todavía. Configúralo en Integraciones.');

        $this->assertSame(0, $this->transport->requestCount());
    }

    private function assertVerifiesAsConnected(string $provider): void
    {
        $this->postJson("/api/v1/integrations/{$provider}/verify")
            ->assertOk()
            ->assertJsonPath('result.status', IntegrationStatus::CONNECTED->value)
            ->assertJsonPath('result.last_checked_at', self::CHECKED_AT);
    }

    private function assertStoredStatus(string $provider, IntegrationStatus $status): void
    {
        $this->assertDatabaseHas('integrations', [
            'account_id' => $this->account->id,
            'provider' => $provider,
            'status' => $status->value,
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
