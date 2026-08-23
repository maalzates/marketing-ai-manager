<?php

declare(strict_types=1);

namespace Tests\Feature\Experiments;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use App\Modules\Experiments\Infrastructure\Persistence\ExperimentWarning;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Every experiment route, read and write, answers 404 for another account's id. Never 403:
 * a 403 would confirm the id exists, which is exactly the fact that must not leak.
 */
class ExperimentAccountIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const string NOT_FOUND_MESSAGE = 'Experiment not found.';

    private Experiment $foreign;

    private Strategy $foreignStrategy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-10 06:00:00'));

        $foreignAccount = Account::factory()->create();
        $this->foreignStrategy = Strategy::factory()->create(['account_id' => $foreignAccount->id]);
        $this->foreign = Experiment::factory()->create([
            'account_id' => $foreignAccount->id,
            'strategy_id' => $this->foreignStrategy->id,
            'title' => 'De la cuenta B',
        ]);

        $user = User::factory()->create();
        $account = Account::factory()->create(['owner_user_id' => $user->id]);
        $user->accounts()->attach($account);

        Sanctum::actingAs($user);
    }

    public function test_does_not_list_another_accounts_experiments(): void
    {
        $this->getJson("/api/v1/strategies/{$this->foreignStrategy->id}/experiments")
            ->assertOk()
            ->assertJsonPath('result.data', []);
    }

    public function test_does_not_read_another_accounts_experiment(): void
    {
        $this->getJson("/api/v1/experiments/{$this->foreign->id}")
            ->assertNotFound()
            ->assertJsonPath('errors.message', self::NOT_FOUND_MESSAGE);
    }

    public function test_does_not_update_another_accounts_experiment(): void
    {
        $this->putJson("/api/v1/experiments/{$this->foreign->id}", ['title' => 'Secuestrado'])
            ->assertNotFound()
            ->assertJsonPath('errors.message', self::NOT_FOUND_MESSAGE);

        $this->assertDatabaseHas('experiments', ['id' => $this->foreign->id, 'title' => 'De la cuenta B']);
    }

    public function test_does_not_read_another_accounts_metrics(): void
    {
        $this->getJson("/api/v1/experiments/{$this->foreign->id}/metrics")
            ->assertNotFound()
            ->assertJsonPath('errors.message', self::NOT_FOUND_MESSAGE);
    }

    public function test_does_not_read_another_accounts_warnings(): void
    {
        ExperimentWarning::factory()->create([
            'account_id' => $this->foreign->account_id,
            'experiment_id' => $this->foreign->id,
        ]);

        $this->getJson("/api/v1/experiments/{$this->foreign->id}/warnings")
            ->assertNotFound()
            ->assertJsonPath('errors.message', self::NOT_FOUND_MESSAGE);
    }

    public function test_does_not_confirm_a_verdict_on_another_accounts_experiment(): void
    {
        $this->postJson("/api/v1/experiments/{$this->foreign->id}/verdict", [
            'verdict' => 'worked',
            'reason' => 'No es mío pero lo cierro igual.',
        ])
            ->assertNotFound()
            ->assertJsonPath('errors.message', self::NOT_FOUND_MESSAGE);

        $this->assertDatabaseHas('experiments', ['id' => $this->foreign->id, 'verdict' => null]);
    }

    public function test_does_not_create_an_experiment_under_another_accounts_strategy(): void
    {
        $this->postJson("/api/v1/strategies/{$this->foreignStrategy->id}/experiments", [
            'type' => 'ads',
            'platform' => 'instagram',
            'title' => 'Colado',
            'hypothesis' => 'Meterme en la estrategia de otra cuenta.',
            'expected_result' => ['metric' => 'cpa', 'operator' => 'lte', 'value' => 20],
            'starts_at' => '2026-09-11',
            'ends_at' => '2026-09-25',
            'max_budget' => 100,
            'status' => 'draft',
        ])
            ->assertNotFound()
            ->assertJsonPath('errors.message', 'Strategy not found.');

        $this->assertDatabaseCount('experiments', 1);
    }
}
