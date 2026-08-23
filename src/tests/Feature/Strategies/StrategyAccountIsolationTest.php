<?php

declare(strict_types=1);

namespace Tests\Feature\Strategies;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Strategies\Domain\Enums\StrategyStatus;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Account B's strategy is reported as missing to account A on every verb, never as
 * forbidden: a 403 would confirm the id exists, which is the leak the 404 avoids.
 */
class StrategyAccountIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const string NOT_FOUND_MESSAGE = 'Strategy not found.';

    private Strategy $foreign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->foreign = Strategy::factory()->create(['name' => 'De la cuenta B']);

        $user = User::factory()->create();
        $account = Account::factory()->create(['owner_user_id' => $user->id]);
        $user->accounts()->attach($account);

        Sanctum::actingAs($user);
    }

    public function test_does_not_list_another_accounts_strategies(): void
    {
        $this->getJson('/api/v1/strategies')
            ->assertOk()
            ->assertJsonPath('result', []);
    }

    public function test_does_not_read_another_accounts_strategy(): void
    {
        $this->getJson("/api/v1/strategies/{$this->foreign->id}")
            ->assertNotFound()
            ->assertJsonPath('errors.message', self::NOT_FOUND_MESSAGE);
    }

    public function test_does_not_update_another_accounts_strategy(): void
    {
        $this->putJson("/api/v1/strategies/{$this->foreign->id}", ['name' => 'Secuestrada'])
            ->assertNotFound()
            ->assertJsonPath('errors.message', self::NOT_FOUND_MESSAGE);

        $this->assertDatabaseHas('strategies', ['id' => $this->foreign->id, 'name' => 'De la cuenta B']);
    }

    public function test_does_not_delete_another_accounts_strategy(): void
    {
        $this->deleteJson("/api/v1/strategies/{$this->foreign->id}")
            ->assertNotFound()
            ->assertJsonPath('errors.message', self::NOT_FOUND_MESSAGE);

        $this->assertDatabaseHas('strategies', ['id' => $this->foreign->id]);
    }

    public function test_does_not_activate_another_accounts_strategy(): void
    {
        $this->foreign->update(['status' => StrategyStatus::Paused]);

        $this->postJson("/api/v1/strategies/{$this->foreign->id}/activate")
            ->assertNotFound()
            ->assertJsonPath('errors.message', self::NOT_FOUND_MESSAGE);

        $this->assertDatabaseHas('strategies', [
            'id' => $this->foreign->id,
            'status' => StrategyStatus::Paused->value,
        ]);
    }

    public function test_does_not_pause_another_accounts_strategy(): void
    {
        $this->postJson("/api/v1/strategies/{$this->foreign->id}/pause")
            ->assertNotFound()
            ->assertJsonPath('errors.message', self::NOT_FOUND_MESSAGE);

        $this->assertDatabaseHas('strategies', [
            'id' => $this->foreign->id,
            'status' => StrategyStatus::Active->value,
        ]);
    }

    public function test_does_not_archive_another_accounts_strategy(): void
    {
        $this->postJson("/api/v1/strategies/{$this->foreign->id}/archive")
            ->assertNotFound()
            ->assertJsonPath('errors.message', self::NOT_FOUND_MESSAGE);

        $this->assertDatabaseHas('strategies', [
            'id' => $this->foreign->id,
            'status' => StrategyStatus::Active->value,
        ]);
        $this->assertDatabaseCount('action_logs', 0);
    }
}
