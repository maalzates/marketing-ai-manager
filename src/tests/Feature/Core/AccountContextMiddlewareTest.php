<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Settings\Infrastructure\Persistence\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * The `account` alias is what every tenant-scoped route hangs off, so it is asserted
 * through one of those routes rather than by invoking the middleware.
 */
class AccountContextMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private const GUARDED_ROUTE = '/api/v1/settings';

    public function test_rejects_a_user_attached_to_no_account(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(self::GUARDED_ROUTE)
            ->assertStatus(Response::HTTP_FORBIDDEN)
            ->assertJsonPath('errors.message', 'Your user is not attached to any account.');
    }

    public function test_rejects_a_user_whose_account_is_inactive(): void
    {
        $account = Account::factory()->inactive()->create();
        Sanctum::actingAs($this->memberOf($account));

        $this->getJson(self::GUARDED_ROUTE)
            ->assertStatus(Response::HTTP_FORBIDDEN)
            ->assertJsonPath('errors.message', 'This account is inactive.');
    }

    public function test_lets_a_member_of_an_active_account_through(): void
    {
        $account = Account::factory()->create();
        Sanctum::actingAs($this->memberOf($account));

        $this->getJson(self::GUARDED_ROUTE)->assertOk();
    }

    public function test_resolves_the_context_to_the_account_the_caller_belongs_to(): void
    {
        $account = Account::factory()->create();
        $other = Account::factory()->create();
        Setting::factory()->forAccount((int) $account->id)->create(['key' => 'preferences.locale', 'value' => 'en']);
        Setting::factory()->forAccount((int) $other->id)->create(['key' => 'preferences.locale', 'value' => 'pt']);

        Sanctum::actingAs($this->memberOf($account));

        $settings = $this->getJson(self::GUARDED_ROUTE)->assertOk()->json('result');

        $this->assertSame('en', $settings['preferences.locale']['value']);
    }

    private function memberOf(Account $account): User
    {
        $user = User::factory()->create();
        $account->users()->attach($user);

        return $user;
    }
}
