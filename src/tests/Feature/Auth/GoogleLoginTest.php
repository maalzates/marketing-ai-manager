<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeTransport;
use Tests\TestCase;

/**
 * The only way into this application. The first login has to create three things in one
 * transaction — user, account, role — and every login after it has to create none of them;
 * getting that wrong either locks people out or silently gives them a second empty account.
 */
class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private const string GOOGLE_SUBJECT = '110000000000000000001';

    private const string EMAIL = 'ana@marca.test';

    private FakeTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-12 08:00:00'));
        $this->seed(RoleSeeder::class);

        // Installed first: the OAuth client factory is a singleton and keeps whichever
        // Guzzle factory it was built with.
        $this->transport = FakeTransport::silent()->install($this->app);
    }

    public function test_a_first_login_creates_the_user_the_account_and_the_role(): void
    {
        $this->login()->assertOk();

        $this->assertDatabaseHas('users', ['email' => self::EMAIL, 'google_id' => self::GOOGLE_SUBJECT]);
        $this->assertDatabaseCount('accounts', 1);
        $this->assertSame(['user'], User::query()->firstOrFail()->roles->pluck('name')->all());
    }

    public function test_a_first_login_returns_a_usable_api_token(): void
    {
        $token = $this->login()->assertOk()->json('result.token');

        $this->assertIsString($token);
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('result.user.email', self::EMAIL);
    }

    public function test_a_second_login_duplicates_neither_the_user_nor_the_account(): void
    {
        $this->login()->assertOk();
        $this->login()->assertOk();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('accounts', 1);
        $this->assertDatabaseCount('account_user', 1);
    }

    public function test_a_second_login_assigns_the_role_only_once(): void
    {
        $this->login()->assertOk();
        $this->login()->assertOk();

        $this->assertSame(['user'], User::query()->firstOrFail()->roles->pluck('name')->all());
    }

    /** The account is adopted by email, so a user who signed in before keeps the one they had. */
    public function test_a_returning_user_keeps_the_account_they_already_owned(): void
    {
        $this->login()->assertOk();
        $accountId = Account::query()->firstOrFail()->id;

        $this->login()->assertOk();

        $this->assertSame($accountId, Account::query()->firstOrFail()->id);
    }

    public function test_an_inactive_user_is_refused_and_gets_no_token(): void
    {
        $this->login()->assertOk();
        User::query()->firstOrFail()->update(['is_active' => false]);

        $response = $this->login();

        $response->assertStatus(403);
        $this->assertNull($response->json('result.token'));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_a_state_this_application_never_issued_is_rejected(): void
    {
        $this->queueGoogleProfile();

        $this->postJson('/api/v1/auth/google/callback', ['code' => 'x', 'state' => 'never-issued'])
            ->assertStatus(422);

        $this->assertDatabaseCount('users', 0);
        $this->assertSame(0, $this->transport->requestCount());
    }

    public function test_the_same_state_cannot_be_replayed(): void
    {
        $state = $this->issuedState();
        $this->queueGoogleProfile();
        $this->callbackWith($state)->assertOk();

        $this->queueGoogleProfile();
        $this->callbackWith($state)->assertStatus(422);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_logout_revokes_only_the_token_that_made_the_call(): void
    {
        $user = User::factory()->create();
        $user->createToken('otro-dispositivo');

        $this->logoutWith($user->createToken('api')->plainTextToken);

        $this->assertSame(1, $user->tokens()->count());
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id, 'name' => 'api']);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id, 'name' => 'otro-dispositivo']);
    }

    public function test_the_revoked_token_stops_authenticating(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('api')->plainTextToken;

        $this->logoutWith($current);

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$current}"])->assertUnauthorized();
    }

    public function test_the_users_other_device_keeps_working_after_a_logout(): void
    {
        $user = User::factory()->create();
        $keep = $user->createToken('otro-dispositivo')->plainTextToken;

        $this->logoutWith($user->createToken('api')->plainTextToken);

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$keep}"])->assertOk();
    }

    public function test_me_reports_the_account_of_the_signed_in_user(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['owner_user_id' => $user->id]);
        $user->accounts()->attach($account);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('result.account.id', $account->id)
            ->assertJsonPath('result.is_admin', false);
    }

    /** The guard caches its resolved user, so the next request needs it cleared to re-read the token. */
    private function logoutWith(string $token): void
    {
        $this->postJson('/api/v1/auth/logout', [], ['Authorization' => "Bearer {$token}"])->assertNoContent();

        $this->app['auth']->forgetGuards();
    }

    private function login(): TestResponse
    {
        $state = $this->issuedState();
        $this->queueGoogleProfile();

        return $this->callbackWith($state);
    }

    private function callbackWith(string $state): TestResponse
    {
        return $this->postJson('/api/v1/auth/google/callback', ['code' => 'auth-code-abc', 'state' => $state]);
    }

    private function issuedState(): string
    {
        return (string) $this->getJson('/api/v1/auth/google/redirect')->assertOk()->json('result.state');
    }

    private function queueGoogleProfile(): void
    {
        $this->transport->queue(
            FakeTransport::json([
                'access_token' => 'ya29.LOGIN-TOKEN',
                'expires_in' => 3599,
                'scope' => 'openid https://www.googleapis.com/auth/userinfo.email',
                'token_type' => 'Bearer',
                'id_token' => 'eyJhbGciOiJSUzI1NiIs.PAYLOAD.SIGNATURE',
            ]),
            FakeTransport::json([
                'sub' => self::GOOGLE_SUBJECT,
                'email' => self::EMAIL,
                'email_verified' => true,
                'name' => 'Ana Marca',
                'picture' => 'https://lh3.googleusercontent.com/a/photo',
            ]),
        );
    }
}
