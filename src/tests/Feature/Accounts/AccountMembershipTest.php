<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Models\User;
use App\Modules\Accounts\Application\DTO\AccountFilterDTO;
use App\Modules\Accounts\Application\DTO\CreateAccountDTO;
use App\Modules\Accounts\Application\Services\AccountService;
use App\Modules\Accounts\Domain\Contracts\AccountRepositoryInterface;
use App\Modules\Accounts\Domain\Exceptions\AccountInactiveException;
use App\Modules\Accounts\Domain\Exceptions\AccountNotFoundException;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Accounts have no HTTP door yet — onboarding is the module that will open one. Until it
 * lands the service is driven through the container, which still exercises the repository
 * and MySQL underneath it.
 */
class AccountMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_account_attaches_the_owner_as_a_member(): void
    {
        $owner = User::factory()->create();

        $account = $this->service()->create(new CreateAccountDTO('Acme Growth', (int) $owner->id));

        $this->assertDatabaseHas('account_user', [
            'account_id' => $account->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_creating_an_account_records_the_owner_and_the_defaults(): void
    {
        $owner = User::factory()->create();

        $account = $this->service()->create(new CreateAccountDTO('Acme Growth', (int) $owner->id, 'EUR', 'Europe/Madrid'));

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Acme Growth',
            'owner_user_id' => $owner->id,
            'currency' => 'EUR',
            'timezone' => 'Europe/Madrid',
            'is_active' => true,
        ]);
    }

    public function test_attaching_a_member_that_is_already_attached_leaves_one_row(): void
    {
        $owner = User::factory()->create();
        $account = $this->service()->create(new CreateAccountDTO('Acme Growth', (int) $owner->id));

        app(AccountRepositoryInterface::class)->attachUser($account, (int) $owner->id);

        $this->assertSame(1, $account->users()->where('users.id', $owner->id)->count());
    }

    public function test_an_account_is_listed_for_each_of_its_members(): void
    {
        $owner = User::factory()->create();
        $account = $this->service()->create(new CreateAccountDTO('Acme Growth', (int) $owner->id));
        Account::factory()->create();

        $this->assertSame([$account->id], $this->service()->findAllForUser((int) $owner->id)->pluck('id')->all());
    }

    public function test_a_missing_account_is_reported_as_not_found(): void
    {
        $this->expectException(AccountNotFoundException::class);

        $this->service()->findById(new AccountFilterDTO(9999));
    }

    public function test_an_inactive_account_is_refused_where_an_active_one_is_required(): void
    {
        $account = Account::factory()->inactive()->create();

        $this->expectException(AccountInactiveException::class);

        $this->service()->findActiveById(new AccountFilterDTO((int) $account->id));
    }

    public function test_deactivating_an_account_persists_the_flag(): void
    {
        $account = Account::factory()->create();

        $this->service()->deactivate(new AccountFilterDTO((int) $account->id));

        $this->assertDatabaseHas('accounts', ['id' => $account->id, 'is_active' => false]);
    }

    private function service(): AccountService
    {
        return app(AccountService::class);
    }
}
