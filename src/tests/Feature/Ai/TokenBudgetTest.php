<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Ai\Domain\Enums\AiTask;
use App\Modules\Audit\Infrastructure\Persistence\LlmUsageLog;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;
use App\Modules\Settings\Infrastructure\Persistence\Setting;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\FakeTransport;
use Tests\TestCase;

/**
 * The guard that stands between a runaway loop and the user's own card. It has to refuse
 * before the request leaves, so every test here also asserts that nothing was sent.
 */
class TokenBudgetTest extends TestCase
{
    use RefreshDatabase;

    private const string MIDDLE_OF_THE_MONTH = '2026-08-15T10:00:00+00:00';

    private Account $account;

    private FakeTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = FakeTransport::silent()->install($this->app);
        $this->account = $this->actAsMemberOfANewAccount();

        Integration::factory()->anthropic()->for($this->account)->create();

        $this->travelTo(self::MIDDLE_OF_THE_MONTH);
    }

    public function test_a_call_past_the_daily_budget_never_reaches_the_provider(): void
    {
        $this->setting('ai.budget.daily_tokens', 1000);
        $this->spend(2000, now());

        $this->suggest()
            ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS)
            ->assertJsonPath('errors.message', 'The daily token budget of 1000 tokens has been reached. Raise it in Settings or wait for the next period.');

        $this->assertSame(0, $this->transport->requestCount());
    }

    public function test_a_call_past_the_monthly_budget_never_reaches_the_provider(): void
    {
        $this->setting('ai.budget.monthly_tokens', 1000);
        $this->spend(2000, now()->startOfMonth());

        $this->suggest()
            ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS)
            ->assertJsonPath('errors.message', 'The monthly token budget of 1000 tokens has been reached. Raise it in Settings or wait for the next period.');

        $this->assertSame(0, $this->transport->requestCount());
    }

    /** Bounds the pre-call estimate, so the reasoning test below discriminates. */
    public function test_a_call_within_the_daily_budget_is_allowed(): void
    {
        $this->setting('ai.budget.daily_tokens', 6000);
        $this->transport->queue(FakeTransport::fixture('anthropic-structured-suggestion.json'));

        $this->suggest()->assertOk();
    }

    /**
     * Reasoning tokens are invisible in the answer and billed at the output rate, so a
     * budget that ignored them would let a thinking model spend several times its cap.
     */
    public function test_hidden_reasoning_counts_against_the_budget(): void
    {
        $this->setting('ai.budget.daily_tokens', 6000);
        LlmUsageLog::factory()->create([
            'account_id' => $this->account->id,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'reasoning_tokens' => 5000,
            'created_at' => now(),
        ]);

        $this->suggest()->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);

        $this->assertSame(0, $this->transport->requestCount());
    }

    /** Zero is how the settings registry spells "no cap". */
    public function test_a_daily_budget_of_zero_lets_the_call_through(): void
    {
        $this->setting('ai.budget.daily_tokens', 0);
        $this->spend(5_000_000, now());
        $this->transport->queue(FakeTransport::fixture('anthropic-structured-suggestion.json'));

        $this->suggest()->assertOk();
    }

    public function test_yesterdays_spend_does_not_count_against_todays_budget(): void
    {
        $this->spend(5000, now()->subDay());
        $this->transport->queue(FakeTransport::fixture('anthropic-structured-suggestion.json'));

        $this->suggest()->assertOk();
    }

    public function test_the_budget_is_counted_per_account(): void
    {
        $this->setting('ai.budget.daily_tokens', 100_000);
        LlmUsageLog::factory()->create([
            'account_id' => Account::factory()->create()->id,
            'input_tokens' => 5_000_000,
            'created_at' => now(),
        ]);
        $this->transport->queue(FakeTransport::fixture('anthropic-structured-suggestion.json'));

        $this->suggest()->assertOk();
    }

    private function suggest(): TestResponse
    {
        return $this->postJson('/api/v1/ai/suggest', [
            'task' => AiTask::FieldSuggestion->value,
            'target' => 'objetivo de la estrategia',
        ]);
    }

    private function spend(int $tokens, CarbonInterface $when): void
    {
        LlmUsageLog::factory()->create([
            'account_id' => $this->account->id,
            'input_tokens' => $tokens,
            'output_tokens' => 0,
            'reasoning_tokens' => 0,
            'created_at' => $when,
        ]);
    }

    private function setting(string $key, mixed $value): void
    {
        Setting::factory()->forAccount($this->account->id)->create(['key' => $key, 'value' => $value]);
    }

    private function actAsMemberOfANewAccount(): Account
    {
        $account = Account::factory()->create();
        $user = User::factory()->create();
        $user->accounts()->attach($account);

        Sanctum::actingAs($user);

        return $account;
    }
}
