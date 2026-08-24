<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Accounts\Infrastructure\Persistence\Role;
use App\Modules\Admin\Infrastructure\Persistence\ApplicationApiKey;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * An issued key is shown once and is unrecoverable afterwards — that is the property that
 * makes a leak of this table worthless. Every test here is one way the plaintext could
 * escape: a later read, the stored row itself, or the action log that records the issuance.
 */
class AdminApiKeyTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-16 10:00:00'));

        $user = User::factory()->create();
        $this->account = Account::factory()->create(['owner_user_id' => $user->id]);
        $user->accounts()->attach($this->account);
        $user->roles()->attach(Role::factory()->admin()->create());

        Sanctum::actingAs($user);
    }

    public function test_issuing_a_key_returns_the_plaintext_exactly_once(): void
    {
        $token = $this->issue()->json('result.token');

        $this->assertIsString($token);
        $this->assertStringStartsWith('mk_live_', $token);

        $listed = $this->getJson('/api/v1/admin/api-keys')->assertOk()->json();

        $this->assertStringNotContainsString($token, (string) json_encode($listed));
    }

    public function test_the_stored_row_holds_only_a_hash_and_a_prefix(): void
    {
        $token = (string) $this->issue()->json('result.token');

        $row = (array) DB::table('application_api_keys')->first();

        $this->assertSame(hash('sha256', $token), $row['token_hash']);
        $this->assertSame(substr($token, 0, 16), $row['prefix']);
        $this->assertStringNotContainsString($token, (string) json_encode($row));
    }

    public function test_the_plaintext_never_reaches_the_action_log(): void
    {
        $token = (string) $this->issue()->json('result.token');

        $log = (array) DB::table('action_logs')->where('action', 'admin.api_key.created')->first();

        $this->assertNotSame([], $log);
        $this->assertStringNotContainsString($token, (string) json_encode($log));
        $this->assertStringContainsString(substr($token, 0, 16), (string) $log['payload']);
    }

    public function test_the_listing_shows_the_prefix_and_nothing_closer_to_the_token(): void
    {
        $token = (string) $this->issue()->json('result.token');

        $this->getJson('/api/v1/admin/api-keys')
            ->assertOk()
            ->assertJsonPath('result.data.0.prefix', substr($token, 0, 16))
            ->assertJsonMissingPath('result.data.0.token_hash');
    }

    public function test_revoking_a_key_stamps_it_instead_of_deleting_it(): void
    {
        $id = (int) $this->issue()->json('result.key.id');

        $this->deleteJson("/api/v1/admin/api-keys/{$id}")->assertOk();

        $this->assertNotNull(ApplicationApiKey::query()->findOrFail($id)->revoked_at);
    }

    public function test_a_key_cannot_be_revoked_twice(): void
    {
        $id = (int) $this->issue()->json('result.key.id');
        $this->deleteJson("/api/v1/admin/api-keys/{$id}")->assertOk();

        $this->deleteJson("/api/v1/admin/api-keys/{$id}")->assertStatus(409);

        $this->assertDatabaseCount('action_logs', 2);
    }

    private function issue(): TestResponse
    {
        return $this->postJson('/api/v1/admin/api-keys', [
            'account_id' => $this->account->id,
            'name' => 'Integración de la agencia',
            'abilities' => ['campaigns:read'],
        ])->assertCreated();
    }
}
