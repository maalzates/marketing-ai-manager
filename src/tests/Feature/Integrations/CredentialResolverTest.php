<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Integrations\Application\Services\CredentialResolver;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use App\Modules\Integrations\Domain\Enums\IntegrationStatus;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\FakeTransport;
use Tests\TestCase;

/**
 * Every module that talks to Google or Meta asks for its token here, and none of them
 * knows whether the one they get was stored an hour ago or minted during their own call.
 * The entry point is a route so the whole chain runs: container → resolver → refresh →
 * repository → MySQL → error envelope.
 */
class CredentialResolverTest extends TestCase
{
    use RefreshDatabase;

    private const string REFRESHED_TOKEN_BODY = '{"access_token":"ya29.RENEWED-Tz70BzhT3Zg","expires_in":3920,"scope":"https://www.googleapis.com/auth/drive.file","token_type":"Bearer"}';

    private const string REVOKED_GRANT_BODY = '{"error":"invalid_grant","error_description":"Token has been expired or revoked."}';

    private Account $account;

    private FakeTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        // Installed before anything resolves a client: the OAuth client factory is a
        // singleton and keeps whichever Guzzle factory it was built with.
        $this->transport = FakeTransport::silent()->install($this->app);
        $this->account = $this->actAsMemberOf(Account::factory()->create());

        Route::middleware(['auth:sanctum', 'account'])->get(
            '/api/testing/access-token/{provider}',
            fn (string $provider, CredentialResolver $resolver, AccountContext $account) => response()->json([
                'result' => ['token' => $resolver->accessToken($account->accountId, IntegrationProvider::from($provider))],
                'errors' => [],
            ]),
        );
    }

    public function test_a_token_inside_the_refresh_window_is_renewed_before_it_is_handed_over(): void
    {
        $integration = Integration::factory()->google()->for($this->account)->create(['expires_at' => now()->addMinutes(2)]);
        $this->transport->queue(FakeTransport::json(self::REFRESHED_TOKEN_BODY));

        $this->getJson('/api/testing/access-token/google')
            ->assertOk()
            ->assertJsonPath('result.token', 'ya29.RENEWED-Tz70BzhT3Zg');

        $this->assertSame('ya29.RENEWED-Tz70BzhT3Zg', $integration->fresh()->credentials['access_token']);
    }

    public function test_a_renewed_grant_keeps_the_refresh_token_google_did_not_send_back(): void
    {
        $integration = Integration::factory()->google()->for($this->account)->create(['expires_at' => now()->addMinutes(2)]);
        $refreshToken = $integration->credentials['refresh_token'];
        $this->transport->queue(FakeTransport::json(self::REFRESHED_TOKEN_BODY));

        $this->getJson('/api/testing/access-token/google')->assertOk();

        $this->assertSame($refreshToken, $integration->fresh()->credentials['refresh_token']);
    }

    public function test_a_token_outside_the_refresh_window_is_handed_over_untouched(): void
    {
        $integration = Integration::factory()->google()->for($this->account)->create(['expires_at' => now()->addHour()]);

        $this->getJson('/api/testing/access-token/google')
            ->assertOk()
            ->assertJsonPath('result.token', $integration->credentials['access_token']);

        $this->assertSame(0, $this->transport->requestCount());
    }

    public function test_a_refused_refresh_asks_the_user_to_authorise_again(): void
    {
        Integration::factory()->google()->for($this->account)->create(['expires_at' => now()->addMinutes(2)]);
        $this->transport->queue(FakeTransport::json(self::REVOKED_GRANT_BODY, Response::HTTP_BAD_REQUEST));

        $this->getJson('/api/testing/access-token/google')
            ->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertJsonPath('errors.message', 'Tu conexión con Google caducó. Vuelve a autorizarla desde Integraciones.');
    }

    public function test_a_refused_refresh_marks_the_connection_expired(): void
    {
        $integration = Integration::factory()->google()->for($this->account)->create(['expires_at' => now()->addMinutes(2)]);
        $this->transport->queue(FakeTransport::json(self::REVOKED_GRANT_BODY, Response::HTTP_BAD_REQUEST));

        $this->getJson('/api/testing/access-token/google')->assertStatus(Response::HTTP_UNAUTHORIZED);

        $this->assertSame(IntegrationStatus::EXPIRED, $integration->fresh()->status);
    }

    public function test_a_provider_with_no_refresh_grant_asks_the_user_to_authorise_again(): void
    {
        Integration::factory()->meta()->for($this->account)->create(['expires_at' => now()->addMinutes(2)]);

        $this->getJson('/api/testing/access-token/meta')
            ->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertJsonPath('errors.message', 'Tu conexión con Meta caducó. Vuelve a autorizarla desde Integraciones.');

        $this->assertSame(0, $this->transport->requestCount());
    }

    public function test_a_provider_that_was_never_connected_is_reported_as_not_connected(): void
    {
        $this->getJson('/api/testing/access-token/google')
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.message', 'No has conectado Google todavía. Configúralo en Integraciones.');

        $this->assertSame(0, $this->transport->requestCount());
    }

    public function test_the_resolved_token_is_the_calling_accounts_own(): void
    {
        $other = Account::factory()->create();
        Integration::factory()->google()->for($other)->create(['credentials' => [
            'access_token' => 'ya29.OTHER-ACCOUNT-TOKEN',
            'refresh_token' => '1//0-other',
            'token_type' => 'Bearer',
        ]]);
        $mine = Integration::factory()->google()->for($this->account)->create(['expires_at' => now()->addHour()]);

        $this->getJson('/api/testing/access-token/google')
            ->assertOk()
            ->assertJsonPath('result.token', $mine->credentials['access_token']);
    }

    private function actAsMemberOf(Account $account): Account
    {
        $user = User::factory()->create();
        $user->accounts()->attach($account);

        Sanctum::actingAs($user);

        return $account;
    }
}
