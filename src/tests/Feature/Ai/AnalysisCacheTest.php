<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Ai\Application\DTO\AiRequestDTO;
use App\Modules\Ai\Application\Services\AiService;
use App\Modules\Ai\Application\Services\AnalysisCacheService;
use App\Modules\Ai\Domain\Contracts\LlmClientFactoryInterface;
use App\Modules\Ai\Domain\Enums\AiTask;
use App\Modules\Ai\Domain\Enums\LlmProvider;
use App\Modules\Ai\Infrastructure\Clients\FakeLlmClient;
use App\Modules\Ai\Infrastructure\Clients\FakeLlmClientFactory;
use App\Modules\Core\Application\Context\AccountContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Re-analysing the same batch of posts is a second bill for an answer already paid for,
 * so the ledger — not a TTL cache — decides whether the model is called at all.
 */
class AnalysisCacheTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = $this->actAsMemberOf(Account::factory()->create());

        $this->app->instance(LlmClientFactoryInterface::class, new FakeLlmClientFactory(
            static fn (int $accountId, LlmProvider $provider) => FakeLlmClient::replaying($provider, 'anthropic-text.json'),
        ));

        Route::middleware(['auth:sanctum', 'account'])->post(
            '/api/testing/ai/analyse',
            fn (Request $request, AnalysisCacheService $cache, AiService $ai, AccountContext $account) => response()->json([
                'result' => $cache->remember(
                    $account->accountId,
                    'competitor_posts',
                    (array) $request->input('input'),
                    fn (): array => ['text' => $ai->complete(new AiRequestDTO(
                        $account->accountId,
                        AiTask::InsightExtraction,
                        (string) json_encode($request->input('input')),
                        userId: $account->userId,
                    ))->text],
                ),
                'errors' => [],
            ]),
        );
    }

    public function test_the_same_input_is_analysed_once_and_answered_twice(): void
    {
        $first = $this->analyse(['posts' => [1, 2, 3]])->assertOk();
        $second = $this->analyse(['posts' => [1, 2, 3]])->assertOk();

        $this->assertSame($first->json('result'), $second->json('result'));
        $this->assertDatabaseCount('llm_usage_logs', 1);
    }

    public function test_a_changed_input_is_analysed_again(): void
    {
        $this->analyse(['posts' => [1, 2, 3]])->assertOk();
        $this->analyse(['posts' => [1, 2, 4]])->assertOk();

        $this->assertDatabaseCount('llm_usage_logs', 2);
    }

    /** Two callers describing the same input in a different key order must hash alike. */
    public function test_the_order_of_the_keys_in_the_input_does_not_start_a_new_analysis(): void
    {
        $this->analyse(['kind' => 'reels', 'posts' => [1, 2]])->assertOk();
        $this->analyse(['posts' => [1, 2], 'kind' => 'reels'])->assertOk();

        $this->assertDatabaseCount('llm_usage_logs', 1);
    }

    public function test_another_accounts_analysis_is_never_served_to_this_one(): void
    {
        $this->analyse(['posts' => [1, 2, 3]])->assertOk();

        $this->actAsMemberOf(Account::factory()->create());
        $this->analyse(['posts' => [1, 2, 3]])->assertOk();

        $this->assertDatabaseCount('ai_analysis_cache', 2);
        $this->assertDatabaseCount('llm_usage_logs', 2);
    }

    private function analyse(array $input): TestResponse
    {
        return $this->postJson('/api/testing/ai/analyse', ['input' => $input]);
    }

    private function actAsMemberOf(Account $account): Account
    {
        $user = User::factory()->create();
        $user->accounts()->attach($account);

        Sanctum::actingAs($user);

        return $account;
    }
}
