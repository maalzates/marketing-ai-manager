<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The account currency is the only currency in the product: the budget fields on the
 * strategy screens read it off the session, so a write that does not come back from
 * `/auth/me` leaves those numbers unlabelled.
 */
class AccountCurrencyTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create();
        Sanctum::actingAs($this->memberOf($this->account));
    }

    public function test_the_currency_saved_from_settings_is_the_one_the_session_hands_back(): void
    {
        $this->putJson('/api/v1/account', ['currency' => 'eur'])
            ->assertOk()
            ->assertJsonPath('result.currency', 'EUR');

        $this->assertDatabaseHas('accounts', ['id' => $this->account->id, 'currency' => 'EUR']);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('result.account.currency', 'EUR');
    }

    /** The account written is the caller's, so an id in the body cannot redirect the write. */
    public function test_an_id_in_the_body_cannot_move_the_write_onto_another_account(): void
    {
        $other = Account::factory()->create(['currency' => 'USD']);

        $this->putJson('/api/v1/account', [
            'currency' => 'eur',
            'account_id' => $other->id,
            'id' => $other->id,
        ])->assertOk();

        $this->assertDatabaseHas('accounts', ['id' => $other->id, 'currency' => 'USD']);
        $this->assertDatabaseHas('accounts', ['id' => $this->account->id, 'currency' => 'EUR']);
    }

    /**
     * The column feeds the decimal-to-minor-units conversion sent to Meta, so anything that
     * is not a three-letter ASCII code has to stop at the door.
     */
    public function test_a_code_that_is_not_three_ascii_letters_is_refused(): void
    {
        foreach (['EURO', 'ÑÑÑ', '12', ''] as $rejected) {
            $this->putJson('/api/v1/account', ['currency' => $rejected])
                ->assertStatus(422)
                ->assertJsonPath('errors.fields.currency.0', fn (string $message): bool => $message !== '');
        }

        $this->assertDatabaseHas('accounts', ['id' => $this->account->id, 'currency' => 'USD']);
    }

    private function memberOf(Account $account): User
    {
        $user = User::factory()->create();
        $account->users()->attach($user);

        return $user;
    }
}
