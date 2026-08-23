<?php

declare(strict_types=1);

namespace Tests\Feature\Strategies;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Brands\Infrastructure\Persistence\BrandProfile;
use App\Modules\Strategies\Domain\Enums\StrategyStatus;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StrategyTransitionsTest extends TestCase
{
    use RefreshDatabase;

    private const string ARCHIVED_MESSAGE = 'This strategy is archived. Activate it before making any change to it.';

    private Account $account;

    private User $user;

    private BrandProfile $brandProfile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->account = Account::factory()->create(['owner_user_id' => $this->user->id]);
        $this->user->accounts()->attach($this->account);
        $this->brandProfile = BrandProfile::factory()->create(['account_id' => $this->account->id]);

        Sanctum::actingAs($this->user);
    }

    public function test_activates_a_paused_strategy(): void
    {
        $strategy = $this->ownStrategy(['status' => StrategyStatus::Paused]);

        $this->postJson("/api/v1/strategies/{$strategy->id}/activate")
            ->assertOk()
            ->assertJsonPath('result.status', StrategyStatus::Active->value);

        $this->assertDatabaseHas('strategies', [
            'id' => $strategy->id,
            'status' => StrategyStatus::Active->value,
        ]);
    }

    public function test_pauses_an_active_strategy(): void
    {
        $strategy = $this->ownStrategy();

        $this->postJson("/api/v1/strategies/{$strategy->id}/pause")
            ->assertOk()
            ->assertJsonPath('result.status', StrategyStatus::Paused->value);

        $this->assertDatabaseHas('strategies', [
            'id' => $strategy->id,
            'status' => StrategyStatus::Paused->value,
        ]);
    }

    public function test_archives_an_active_strategy(): void
    {
        $strategy = $this->ownStrategy();

        $this->postJson("/api/v1/strategies/{$strategy->id}/archive")
            ->assertOk()
            ->assertJsonPath('result.status', StrategyStatus::Archived->value);

        $this->assertDatabaseHas('strategies', [
            'id' => $strategy->id,
            'status' => StrategyStatus::Archived->value,
        ]);
    }

    public function test_records_the_archive_in_the_action_log(): void
    {
        $strategy = $this->ownStrategy(['name' => 'Captación local', 'north_star_metric' => 'cost_per_lead']);

        $this->postJson("/api/v1/strategies/{$strategy->id}/archive")->assertOk();

        $this->assertDatabaseHas('action_logs', [
            'account_id' => $this->account->id,
            'user_id' => $this->user->id,
            'action' => 'strategy.archived',
            'entity_type' => 'strategy',
            'entity_id' => $strategy->id,
            'origin' => 'ui',
        ]);
    }

    public function test_refuses_to_pause_an_archived_strategy(): void
    {
        $strategy = $this->ownStrategy(['status' => StrategyStatus::Archived]);

        $this->postJson("/api/v1/strategies/{$strategy->id}/pause")
            ->assertStatus(409)
            ->assertJsonPath('errors.message', self::ARCHIVED_MESSAGE);

        $this->assertDatabaseHas('strategies', [
            'id' => $strategy->id,
            'status' => StrategyStatus::Archived->value,
        ]);
    }

    public function test_refuses_to_archive_an_already_archived_strategy(): void
    {
        $strategy = $this->ownStrategy(['status' => StrategyStatus::Archived]);

        $this->postJson("/api/v1/strategies/{$strategy->id}/archive")
            ->assertStatus(409)
            ->assertJsonPath('errors.message', self::ARCHIVED_MESSAGE);

        $this->assertDatabaseCount('action_logs', 0);
    }

    public function test_refuses_to_edit_an_archived_strategy(): void
    {
        $strategy = $this->ownStrategy(['status' => StrategyStatus::Archived, 'objective' => 'Objetivo congelado']);

        $this->putJson("/api/v1/strategies/{$strategy->id}", ['objective' => 'Objetivo nuevo'])
            ->assertStatus(409)
            ->assertJsonPath('errors.message', self::ARCHIVED_MESSAGE);

        $this->assertDatabaseHas('strategies', ['id' => $strategy->id, 'objective' => 'Objetivo congelado']);
    }

    /**
     * Reactivation is a step of its own: nothing else moves an archived strategy, and it
     * has to be asked for explicitly rather than implied by an edit.
     */
    public function test_reactivates_an_archived_strategy_only_through_the_dedicated_activate_step(): void
    {
        $strategy = $this->ownStrategy(['status' => StrategyStatus::Archived]);

        $this->putJson("/api/v1/strategies/{$strategy->id}", ['name' => 'Reanimada'])->assertStatus(409);

        $this->postJson("/api/v1/strategies/{$strategy->id}/activate")
            ->assertOk()
            ->assertJsonPath('result.status', StrategyStatus::Active->value);

        $this->putJson("/api/v1/strategies/{$strategy->id}", ['name' => 'Reanimada'])
            ->assertOk()
            ->assertJsonPath('result.name', 'Reanimada');
    }

    public function test_deletes_an_archived_strategy(): void
    {
        $strategy = $this->ownStrategy(['status' => StrategyStatus::Archived]);

        $this->deleteJson("/api/v1/strategies/{$strategy->id}")->assertNoContent();

        $this->assertDatabaseMissing('strategies', ['id' => $strategy->id]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function ownStrategy(array $attributes = []): Strategy
    {
        return Strategy::factory()->create([
            'account_id' => $this->account->id,
            'brand_profile_id' => $this->brandProfile->id,
            ...$attributes,
        ]);
    }
}
