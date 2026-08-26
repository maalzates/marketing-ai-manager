<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Integrations\Domain\Enums\IntegrationStatus;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\EmulatesASecondRequest;
use Tests\Support\FakeTransport;
use Tests\TestCase;

/**
 * The callback is the only public route in the module: the provider redirects a browser
 * to it with no session and no bearer token, so the `state` is the sole thing tying the
 * grant to an account. Every way of getting it wrong is a way of attaching somebody
 * else's Google account to your workspace.
 */
class IntegrationOAuthTest extends TestCase
{
    use EmulatesASecondRequest, RefreshDatabase;

    private const string TOKEN_BODY = '{"access_token":"ya29.a0AfB_byC3nQ7xK1mR9tPwLdX2vHgY8sZq","expires_in":3599,"refresh_token":"1//0gK8sLpQ3vXmZCgYIARAAGBASNwF-L9Ir","scope":"openid email profile https://www.googleapis.com/auth/drive.file","token_type":"Bearer","id_token":"eyJhbGciOiJSUzI1NiJ9.eyJpc3MiOiJodHRwczovL2FjY291bnRzLmdvb2dsZS5jb20ifQ.SIGNATURE"}';

    private const string TOKEN_BODY_WITHOUT_REFRESH = '{"access_token":"ya29.a0AfB_byC3nQ7xK1mR9tPwLdX2vHgY8sZq","expires_in":3599,"scope":"openid email profile","token_type":"Bearer"}';

    private const string USERINFO_BODY = '{"sub":"110248495921238986420","name":"Jane Doe","email":"jane@example.com","email_verified":true}';

    private Account $account;

    private FakeTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        // Before the first request: the OAuth client factory is a singleton and captures the
        // Guzzle factory it is built with, so a fake installed later never reaches it.
        $this->transport = FakeTransport::silent()->install($this->app);
        $this->account = $this->actAsMemberOfANewAccount();
    }

    public function test_the_authorisation_url_asks_google_for_a_refresh_token(): void
    {
        $url = $this->getJson('/api/v1/integrations/google/oauth/redirect')->assertOk()->json('result.url');

        $this->assertStringContainsString('access_type=offline', $url);
        $this->assertStringContainsString('prompt=consent', $url);
    }

    public function test_the_authorisation_url_asks_for_the_drive_scope_alongside_sign_in(): void
    {
        $url = $this->getJson('/api/v1/integrations/google/oauth/redirect')->assertOk()->json('result.url');

        $this->assertStringContainsString(urlencode('https://www.googleapis.com/auth/drive.file'), $url);
    }

    public function test_the_authorisation_url_carries_the_callback_this_deployment_answers_on(): void
    {
        $url = $this->getJson('/api/v1/integrations/google/oauth/redirect')->assertOk()->json('result.url');

        $this->assertStringContainsString(urlencode(route('integrations.oauth.callback', ['provider' => 'google'])), $url);
    }

    public function test_a_provider_that_does_not_use_oauth_has_no_authorisation_url(): void
    {
        $this->getJson('/api/v1/integrations/anthropic/oauth/redirect')
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.message', 'Anthropic no se conecta con OAuth.');
    }

    public function test_the_completed_callback_stores_the_grant_as_connected(): void
    {
        $state = $this->issuedState();
        $this->transport->queue(
            FakeTransport::json(self::TOKEN_BODY),
            FakeTransport::json(self::USERINFO_BODY),
        );

        $this->betweenRequests();
        $this->get("/api/v1/integrations/google/oauth/callback?code=4/0AY0e-g7&state={$state}")
            ->assertRedirect('/settings?integration=google&status=connected');

        $this->assertDatabaseHas('integrations', [
            'account_id' => $this->account->id,
            'provider' => 'google',
            'status' => IntegrationStatus::CONNECTED->value,
            'external_account_id' => '110248495921238986420',
        ]);
    }

    public function test_the_completed_callback_never_returns_the_token_it_stored(): void
    {
        $state = $this->issuedState();
        $this->transport->queue(
            FakeTransport::json(self::TOKEN_BODY),
            FakeTransport::json(self::USERINFO_BODY),
        );

        $this->betweenRequests();
        $response = $this->get("/api/v1/integrations/google/oauth/callback?code=4/0AY0e-g7&state={$state}");

        $landing = $response->headers->get('Location').$response->getContent();

        $this->assertStringNotContainsString('ya29.a0AfB_byC3nQ7xK1mR9tPwLdX2vHgY8sZq', $landing);
        $this->assertStringNotContainsString('1//0gK8sLpQ3vXmZCgYIARAAGBASNwF-L9Ir', $landing);
    }

    public function test_the_stored_grant_is_encrypted_at_rest(): void
    {
        $state = $this->issuedState();
        $this->transport->queue(
            FakeTransport::json(self::TOKEN_BODY),
            FakeTransport::json(self::USERINFO_BODY),
        );

        $this->betweenRequests();
        $this->get("/api/v1/integrations/google/oauth/callback?code=4/0AY0e-g7&state={$state}")
            ->assertRedirect('/settings?integration=google&status=connected');

        $stored = (string) DB::table('integrations')->where('account_id', $this->account->id)->value('credentials');

        $this->assertStringNotContainsString('1//0gK8sLpQ3vXmZCgYIARAAGBASNwF-L9Ir', $stored);
    }

    public function test_a_callback_without_a_state_is_refused(): void
    {
        $this->get('/api/v1/integrations/google/oauth/callback?code=4/0AY0e-g7')
            ->assertRedirect('/settings?'.http_build_query(['status' => 'error', 'message' => 'La autorización no se pudo correlacionar. Vuelve a iniciarla desde Integraciones.']));

        $this->assertSame(0, $this->transport->requestCount());
        $this->assertDatabaseCount('integrations', 0);
    }

    public function test_a_forged_state_is_refused_without_calling_the_provider(): void
    {
        $forged = base64_encode('{"account_id":1,"provider":"google","nonce":"whatever"}').'.deadbeef';

        $this->get("/api/v1/integrations/google/oauth/callback?code=4/0AY0e-g7&state={$forged}")
            ->assertRedirect('/settings?'.http_build_query(['status' => 'error', 'message' => 'La autorización no se pudo correlacionar. Vuelve a iniciarla desde Integraciones.']));

        $this->assertSame(0, $this->transport->requestCount());
        $this->assertDatabaseCount('integrations', 0);
    }

    public function test_a_state_cannot_be_replayed(): void
    {
        $state = $this->issuedState();
        $this->transport->queue(
            FakeTransport::json(self::TOKEN_BODY),
            FakeTransport::json(self::USERINFO_BODY),
        );

        $this->betweenRequests();
        $this->get("/api/v1/integrations/google/oauth/callback?code=4/0AY0e-g7&state={$state}")
            ->assertRedirect('/settings?integration=google&status=connected');

        $this->betweenRequests();
        $this->get("/api/v1/integrations/google/oauth/callback?code=4/0AY0e-g7&state={$state}")
            ->assertRedirect('/settings?'.http_build_query(['status' => 'error', 'message' => 'La autorización no se pudo correlacionar. Vuelve a iniciarla desde Integraciones.']));
    }

    public function test_a_state_issued_for_one_provider_cannot_complete_another(): void
    {
        $state = $this->issuedState();

        $this->betweenRequests();
        $this->get("/api/v1/integrations/meta/oauth/callback?code=4/0AY0e-g7&state={$state}")
            ->assertRedirect('/settings?'.http_build_query(['status' => 'error', 'message' => 'La autorización no se pudo correlacionar. Vuelve a iniciarla desde Integraciones.']));

        $this->assertSame(0, $this->transport->requestCount());
        $this->assertDatabaseCount('integrations', 0);
    }

    public function test_a_refused_authorisation_stores_nothing(): void
    {
        $state = $this->issuedState();

        $this->betweenRequests();
        $this->get("/api/v1/integrations/google/oauth/callback?error=access_denied&state={$state}")
            ->assertRedirect('/settings?'.http_build_query(['status' => 'error', 'message' => 'No autorizaste el acceso a Google. La conexión no se guardó.']));

        $this->assertSame(0, $this->transport->requestCount());
        $this->assertDatabaseCount('integrations', 0);
    }

    /** Without a refresh token the connection would die an hour later, so it is not stored. */
    public function test_a_grant_google_returned_without_a_refresh_token_is_refused(): void
    {
        $state = $this->issuedState();
        $this->transport->queue(FakeTransport::json(self::TOKEN_BODY_WITHOUT_REFRESH));

        $this->betweenRequests();
        $this->get("/api/v1/integrations/google/oauth/callback?code=4/0AY0e-g7&state={$state}")
            ->assertRedirect('/settings?'.http_build_query(['status' => 'error', 'message' => 'Google no devolvió un permiso permanente. Revoca el acceso de la aplicación en tu cuenta de Google y vuelve a autorizarla.']));

        $this->assertDatabaseCount('integrations', 0);
    }

    public function test_the_grant_is_stored_on_the_account_that_started_the_flow(): void
    {
        $state = $this->issuedState();
        $other = Account::factory()->create();
        Integration::factory()->anthropic()->for($other)->create();
        $this->transport->queue(
            FakeTransport::json(self::TOKEN_BODY),
            FakeTransport::json(self::USERINFO_BODY),
        );

        $this->betweenRequests();
        $this->get("/api/v1/integrations/google/oauth/callback?code=4/0AY0e-g7&state={$state}")
            ->assertRedirect('/settings?integration=google&status=connected');

        $this->assertDatabaseHas('integrations', ['account_id' => $this->account->id, 'provider' => 'google']);
        $this->assertDatabaseMissing('integrations', ['account_id' => $other->id, 'provider' => 'google']);
    }

    public function test_asking_for_an_authorisation_url_requires_authentication(): void
    {
        app('auth')->forgetGuards();

        $this->getJson('/api/v1/integrations/google/oauth/redirect')->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    private function issuedState(): string
    {
        $url = $this->getJson('/api/v1/integrations/google/oauth/redirect')->assertOk()->json('result.url');

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return (string) $query['state'];
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
