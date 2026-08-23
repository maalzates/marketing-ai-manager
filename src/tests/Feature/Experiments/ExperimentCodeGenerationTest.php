<?php

declare(strict_types=1);

namespace Tests\Feature\Experiments;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Carbon\CarbonImmutable;
use Database\Seeders\DomainKnowledgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\EmulatesASecondRequest;
use Tests\TestCase;

/**
 * The code is the handle the user and the assistant refer to an experiment by, so it is
 * the Service's to mint: sequential, zero-padded, and numbered per account rather than
 * globally — account B must not be able to tell how many experiments account A has run.
 */
class ExperimentCodeGenerationTest extends TestCase
{
    use EmulatesASecondRequest;
    use RefreshDatabase;

    private Strategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-01 09:00:00'));
        $this->seed(DomainKnowledgeSeeder::class);

        $user = User::factory()->create();
        $account = Account::factory()->create(['owner_user_id' => $user->id]);
        $user->accounts()->attach($account);
        $this->strategy = Strategy::factory()->create([
            'account_id' => $account->id,
            'monthly_budget' => 2000,
        ]);

        Sanctum::actingAs($user);
    }

    public function test_the_first_experiment_of_an_account_is_numbered_one(): void
    {
        $this->postJson($this->storeUrl($this->strategy), $this->payload())
            ->assertCreated()
            ->assertJsonPath('result.code', 'EXP-001');
    }

    public function test_the_code_increments_with_every_experiment_of_the_account(): void
    {
        $this->postJson($this->storeUrl($this->strategy), $this->payload())->assertCreated();
        $this->postJson($this->storeUrl($this->strategy), $this->payload())->assertCreated();

        $this->postJson($this->storeUrl($this->strategy), $this->payload())
            ->assertCreated()
            ->assertJsonPath('result.code', 'EXP-003');
    }

    public function test_the_code_ignores_a_client_supplied_one(): void
    {
        $this->postJson($this->storeUrl($this->strategy), $this->payload(['code' => 'HACK-999']))
            ->assertCreated()
            ->assertJsonPath('result.code', 'EXP-001');

        $this->assertDatabaseMissing('experiments', ['code' => 'HACK-999']);
    }

    public function test_each_account_starts_its_own_numbering_at_one(): void
    {
        $this->postJson($this->storeUrl($this->strategy), $this->payload())
            ->assertCreated()
            ->assertJsonPath('result.code', 'EXP-001');

        $this->betweenRequests();
        $secondStrategy = $this->actAsASecondAccount();

        $this->postJson($this->storeUrl($secondStrategy), $this->payload())
            ->assertCreated()
            ->assertJsonPath('result.code', 'EXP-001');

        $this->assertSame(2, Experiment::query()->where('code', 'EXP-001')->count());
    }

    private function actAsASecondAccount(): Strategy
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['owner_user_id' => $user->id]);
        $user->accounts()->attach($account);

        Sanctum::actingAs($user);

        return Strategy::factory()->create(['account_id' => $account->id, 'monthly_budget' => 2000]);
    }

    private function storeUrl(Strategy $strategy): string
    {
        return "/api/v1/strategies/{$strategy->id}/experiments";
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'type' => 'ads',
            'platform' => 'instagram',
            'title' => 'Creativo A contra creativo B',
            'hypothesis' => 'El creativo con testimonio baja el CPA.',
            'expected_result' => ['metric' => 'cpa', 'operator' => 'lte', 'value' => 20],
            'starts_at' => '2026-09-01',
            'ends_at' => '2026-09-15',
            'max_budget' => 100,
            'status' => 'draft',
            ...$overrides,
        ];
    }
}
