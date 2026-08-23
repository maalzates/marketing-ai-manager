<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Audit\Infrastructure\Persistence\ApifyUsageLog;
use App\Modules\Audit\Infrastructure\Persistence\LlmUsageLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * The consumption ledger is what the account is billed against, so the tenancy assertion
 * here matters as much as the arithmetic.
 */
class UsageSummaryTest extends TestCase
{
    use RefreshDatabase;

    private const RANGE = 'from=2026-08-01&to=2026-08-31';

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-08-23 10:00:00');

        $this->account = Account::factory()->create();
        $user = User::factory()->create();
        $this->account->users()->attach($user);
        Sanctum::actingAs($user);
    }

    public function test_groups_llm_usage_by_day(): void
    {
        $this->llm(['created_at' => '2026-08-10 08:00:00', 'input_tokens' => 100, 'output_tokens' => 20, 'reasoning_tokens' => 5]);
        $this->llm(['created_at' => '2026-08-10 20:00:00', 'input_tokens' => 300, 'output_tokens' => 40, 'reasoning_tokens' => 0]);
        $this->llm(['created_at' => '2026-08-11 08:00:00', 'input_tokens' => 10, 'output_tokens' => 1, 'reasoning_tokens' => 0]);

        $rows = $this->summary()['llm'];

        $this->assertSame(['2026-08-10', '2026-08-11'], array_column($rows, 'label'));
        $this->assertSame(465, $rows[0]['total_tokens']);
        $this->assertSame(2, $rows[0]['calls']);
    }

    public function test_groups_llm_usage_by_feature(): void
    {
        $this->llm(['feature' => 'chat', 'input_tokens' => 100, 'output_tokens' => 20, 'reasoning_tokens' => 5]);
        $this->llm(['feature' => 'guardian', 'input_tokens' => 7, 'output_tokens' => 3, 'reasoning_tokens' => 0]);

        $rows = $this->summary('feature')['llm'];

        $this->assertSame(['chat', 'guardian'], array_column($rows, 'label'));
        $this->assertSame(125, $rows[0]['total_tokens']);
        $this->assertSame(10, $rows[1]['total_tokens']);
    }

    public function test_the_total_adds_reasoning_tokens_to_input_and_output(): void
    {
        $this->llm(['input_tokens' => 1, 'output_tokens' => 2, 'reasoning_tokens' => 4]);

        $this->assertSame(7, $this->summary()['llm'][0]['total_tokens']);
    }

    public function test_another_accounts_usage_never_appears(): void
    {
        $this->llm(['input_tokens' => 10, 'output_tokens' => 0, 'reasoning_tokens' => 0]);
        LlmUsageLog::factory()->create([
            'account_id' => Account::factory()->create()->id,
            'created_at' => '2026-08-10 08:00:00',
            'input_tokens' => 999999,
            'output_tokens' => 999999,
            'reasoning_tokens' => 0,
        ]);

        $rows = $this->summary()['llm'];

        $this->assertCount(1, $rows);
        $this->assertSame(10, $rows[0]['total_tokens']);
    }

    public function test_rows_outside_the_range_are_left_out(): void
    {
        $this->llm(['created_at' => '2026-07-31 23:00:00']);
        $this->llm(['created_at' => '2026-09-01 01:00:00']);

        $this->assertSame([], $this->summary()['llm']);
    }

    public function test_apify_runs_are_reported_beside_the_llm_spend(): void
    {
        ApifyUsageLog::factory()->create([
            'account_id' => $this->account->id,
            'actor_id' => 'apify~instagram-scraper',
            'created_at' => '2026-08-10 08:00:00',
            'results_count' => 30,
        ]);
        ApifyUsageLog::factory()->create([
            'account_id' => Account::factory()->create()->id,
            'created_at' => '2026-08-10 08:00:00',
            'results_count' => 900,
        ]);

        $rows = $this->summary('feature')['apify'];

        $this->assertSame(['apify~instagram-scraper'], array_column($rows, 'label'));
        $this->assertSame(30, $rows[0]['results']);
    }

    public function test_rejects_a_grouping_it_does_not_support(): void
    {
        $this->getJson('/api/v1/usage?'.self::RANGE.'&group_by=provider')
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.status_code', Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function summary(string $groupBy = 'day'): array
    {
        return $this->getJson('/api/v1/usage?'.self::RANGE."&group_by={$groupBy}")->assertOk()->json('result');
    }

    private function llm(array $attributes = []): LlmUsageLog
    {
        return LlmUsageLog::factory()->create([
            'created_at' => '2026-08-10 08:00:00',
            ...$attributes,
            'account_id' => $this->account->id,
        ]);
    }
}
