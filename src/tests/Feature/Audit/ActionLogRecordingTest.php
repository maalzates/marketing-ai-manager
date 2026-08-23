<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Audit\Domain\Enums\ActionOrigin;
use App\Modules\Audit\Infrastructure\Persistence\ActionLog;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Archiving a strategy is the first route that writes to the ledger, so it is the one the
 * write path is asserted through.
 */
class ActionLogRecordingTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create();
        $this->user = User::factory()->create();
        $this->account->users()->attach($this->user);
        Sanctum::actingAs($this->user);
    }

    public function test_archiving_a_strategy_writes_exactly_one_action_log_row(): void
    {
        $strategy = Strategy::factory()->create(['account_id' => $this->account->id]);

        $this->postJson("/api/v1/strategies/{$strategy->id}/archive")->assertOk();

        $this->assertSame(1, ActionLog::query()->count());
    }

    public function test_the_recorded_row_names_the_action_its_origin_and_the_entity(): void
    {
        $strategy = Strategy::factory()->create(['account_id' => $this->account->id]);

        $this->postJson("/api/v1/strategies/{$strategy->id}/archive")->assertOk();

        $log = ActionLog::query()->sole();

        $this->assertSame('strategy.archived', $log->action);
        $this->assertSame(ActionOrigin::UI, $log->origin);
        $this->assertSame('strategy', $log->entity_type);
        $this->assertSame((int) $strategy->id, $log->entity_id);
    }

    public function test_the_recorded_row_belongs_to_the_acting_account_and_user(): void
    {
        $strategy = Strategy::factory()->create(['account_id' => $this->account->id]);

        $this->postJson("/api/v1/strategies/{$strategy->id}/archive")->assertOk();

        $log = ActionLog::query()->sole();

        $this->assertSame((int) $this->account->id, $log->account_id);
        $this->assertSame((int) $this->user->id, $log->user_id);
    }

    public function test_the_recorded_payload_carries_the_fields_the_ledger_promises(): void
    {
        $strategy = Strategy::factory()->create([
            'account_id' => $this->account->id,
            'name' => 'Q4 growth push',
            'north_star_metric' => 'cost_per_lead',
        ]);

        $this->postJson("/api/v1/strategies/{$strategy->id}/archive")->assertOk();

        $this->assertSame(
            ['name' => 'Q4 growth push', 'north_star_metric' => 'cost_per_lead'],
            ActionLog::query()->sole()->payload,
        );
    }

    public function test_a_rejected_request_writes_nothing_to_the_ledger(): void
    {
        $strategy = Strategy::factory()->create(['account_id' => Account::factory()->create()->id]);

        $this->postJson("/api/v1/strategies/{$strategy->id}/archive")->assertNotFound();

        $this->assertSame(0, ActionLog::query()->count());
    }
}
