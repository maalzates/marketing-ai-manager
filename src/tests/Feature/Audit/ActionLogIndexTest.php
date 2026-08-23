<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Audit\Domain\Enums\ActionOrigin;
use App\Modules\Audit\Infrastructure\Persistence\ActionLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ActionLogIndexTest extends TestCase
{
    use RefreshDatabase;

    private const ROUTE = '/api/v1/action-logs';

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

    public function test_lists_only_the_rows_of_the_calling_account(): void
    {
        $mine = $this->log(['action' => 'strategy.archived']);
        ActionLog::factory()->create(['account_id' => Account::factory()->create()->id]);

        $data = $this->getJson(self::ROUTE)->assertOk()->json('result.data');

        $this->assertSame([$mine->id], array_column($data, 'id'));
    }

    public function test_filters_by_action(): void
    {
        $wanted = $this->log(['action' => 'strategy.archived']);
        $this->log(['action' => 'experiment.closed']);

        $data = $this->getJson(self::ROUTE.'?action=strategy.archived')->assertOk()->json('result.data');

        $this->assertSame([$wanted->id], array_column($data, 'id'));
    }

    public function test_filters_by_origin(): void
    {
        $wanted = $this->log(['origin' => ActionOrigin::CHAT]);
        $this->log(['origin' => ActionOrigin::UI]);

        $data = $this->getJson(self::ROUTE.'?origin=chat')->assertOk()->json('result.data');

        $this->assertSame([$wanted->id], array_column($data, 'id'));
    }

    public function test_filters_by_a_date_range(): void
    {
        $wanted = $this->log(['created_at' => '2026-08-20 09:00:00']);
        $this->log(['created_at' => '2026-08-10 09:00:00']);
        $this->log(['created_at' => '2026-08-23 09:00:00']);

        $data = $this->getJson(self::ROUTE.'?from=2026-08-19 00:00:00&to=2026-08-21 00:00:00')
            ->assertOk()
            ->json('result.data');

        $this->assertSame([$wanted->id], array_column($data, 'id'));
    }

    public function test_returns_the_newest_row_first(): void
    {
        $older = $this->log(['created_at' => '2026-08-20 09:00:00']);
        $newer = $this->log(['created_at' => '2026-08-22 09:00:00']);

        $data = $this->getJson(self::ROUTE)->assertOk()->json('result.data');

        $this->assertSame([$newer->id, $older->id], array_column($data, 'id'));
    }

    public function test_paginates_the_result(): void
    {
        ActionLog::factory()->count(5)->create(['account_id' => $this->account->id]);

        $result = $this->getJson(self::ROUTE.'?per_page=2&page=2')->assertOk()->json('result');

        $this->assertCount(2, $result['data']);
        $this->assertSame(5, $result['total']);
        $this->assertSame(2, $result['current_page']);
    }

    public function test_rejects_a_page_size_beyond_the_cap(): void
    {
        $this->getJson(self::ROUTE.'?per_page=500')
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.fields.per_page.0', 'The per page field must not be greater than 100.');
    }

    public function test_rejects_a_range_that_ends_before_it_starts(): void
    {
        $this->getJson(self::ROUTE.'?from=2026-08-20&to=2026-08-10')
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.status_code', Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function log(array $attributes): ActionLog
    {
        return ActionLog::factory()->create([...$attributes, 'account_id' => $this->account->id]);
    }
}
