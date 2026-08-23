<?php

declare(strict_types=1);

namespace Tests\Feature\Competitors;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Competitors\Application\Jobs\SyncCompetitorJob;
use App\Modules\Competitors\Infrastructure\Persistence\Competitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\FakeTransport;
use Tests\TestCase;

/**
 * core.md §10.4: scraping is always asynchronous. The door acknowledges and the caller keeps
 * reading the rows it already has — a synchronous scrape would hold a web worker for minutes
 * and bill the user's Apify credit inside a request that may already have timed out.
 */
class CompetitorSyncEndpointTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private FakeTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = FakeTransport::silent()->install($this->app);
        $this->account = $this->actAsMemberOfANewAccount();
    }

    public function test_requesting_a_sync_is_accepted_rather_than_answered_with_the_scrape(): void
    {
        Bus::fake();
        $competitor = Competitor::factory()->create(['account_id' => $this->account->id]);

        $this->postJson("/api/v1/competitors/{$competitor->id}/sync")
            ->assertStatus(Response::HTTP_ACCEPTED)
            ->assertJsonPath('result.id', $competitor->id);
    }

    public function test_requesting_a_sync_dispatches_the_pipeline_instead_of_scraping_inline(): void
    {
        Bus::fake();
        $competitor = Competitor::factory()->create(['account_id' => $this->account->id]);

        $this->postJson("/api/v1/competitors/{$competitor->id}/sync")->assertStatus(Response::HTTP_ACCEPTED);

        Bus::assertDispatched(SyncCompetitorJob::class);
        $this->assertSame(0, $this->transport->requestCount());
    }

    public function test_a_competitor_of_another_account_cannot_be_synced(): void
    {
        Bus::fake();
        $competitor = Competitor::factory()->create(['account_id' => Account::factory()->create()->id]);

        $this->postJson("/api/v1/competitors/{$competitor->id}/sync")
            ->assertStatus(Response::HTTP_NOT_FOUND);

        Bus::assertNothingDispatched();
    }

    public function test_a_competitor_of_another_account_cannot_be_read(): void
    {
        $competitor = Competitor::factory()->create(['account_id' => Account::factory()->create()->id]);

        $this->getJson("/api/v1/competitors/{$competitor->id}")->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function test_the_index_lists_only_the_competitors_of_the_callers_account(): void
    {
        Competitor::factory()->create(['account_id' => $this->account->id, 'handle' => 'mine']);
        Competitor::factory()->create(['account_id' => Account::factory()->create()->id, 'handle' => 'theirs']);

        $this->getJson('/api/v1/competitors')
            ->assertOk()
            ->assertJsonCount(1, 'result')
            ->assertJsonPath('result.0.handle', 'mine');
    }

    public function test_posts_of_a_competitor_of_another_account_are_not_readable(): void
    {
        $competitor = Competitor::factory()->create(['account_id' => Account::factory()->create()->id]);

        $this->getJson("/api/v1/competitors/{$competitor->id}/posts")->assertStatus(Response::HTTP_NOT_FOUND);
    }

    private function actAsMemberOfANewAccount(): Account
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['owner_user_id' => $user->id]);
        $user->accounts()->attach($account);

        Sanctum::actingAs($user);

        return $account;
    }
}
