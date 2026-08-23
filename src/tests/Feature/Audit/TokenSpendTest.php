<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Audit\Infrastructure\Persistence\LlmUsageLog;
use App\Modules\Settings\Infrastructure\Persistence\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * The spend totals are read by the budget guard, so /api/v1/ai/suggest is the door they are
 * asserted through: the guard runs — and refuses — before any client is built, which is why
 * none of this needs a provider.
 */
class TokenSpendTest extends TestCase
{
    use RefreshDatabase;

    private const CAP = 50000;

    private const NO_CAP = 0;

    private const OVER_THE_CAP = 90000;

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

    /**
     * @return array<string, list<string>>
     */
    public static function billedColumns(): array
    {
        return [
            'prompt tokens' => ['input_tokens'],
            'completion tokens' => ['output_tokens'],
            'hidden reasoning tokens' => ['reasoning_tokens'],
        ];
    }

    #[DataProvider('billedColumns')]
    public function test_todays_spend_counts(string $column): void
    {
        $this->budget(daily: self::CAP, monthly: self::NO_CAP);
        $this->spend(['created_at' => '2026-08-23 09:00:00', $column => self::OVER_THE_CAP]);

        $this->suggest()
            ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS)
            ->assertJsonPath('errors.message', 'The daily token budget of 50000 tokens has been reached. Raise it in Settings or wait for the next period.');
    }

    public function test_an_earlier_day_of_the_month_counts_towards_the_month_but_not_towards_today(): void
    {
        $this->budget(daily: self::CAP, monthly: self::CAP);
        $this->spend(['created_at' => '2026-08-05 09:00:00', 'input_tokens' => self::OVER_THE_CAP]);

        // The daily cap is checked first, so naming the monthly one is what proves the
        // earlier day was left out of today's total.
        $this->suggest()
            ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS)
            ->assertJsonPath('errors.message', 'The monthly token budget of 50000 tokens has been reached. Raise it in Settings or wait for the next period.');
    }

    public function test_a_previous_months_spend_is_charged_to_neither_period(): void
    {
        $this->budget(daily: self::CAP, monthly: self::CAP);
        $this->spend(['created_at' => '2026-07-20 09:00:00', 'input_tokens' => self::OVER_THE_CAP]);

        // Past the guard the call stops at the missing provider key, which is the point:
        // it was never refused on budget.
        $this->suggest()->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_another_accounts_spend_is_not_charged_to_this_one(): void
    {
        $this->budget(daily: self::CAP, monthly: self::CAP);
        LlmUsageLog::factory()->create([
            'account_id' => Account::factory()->create()->id,
            'created_at' => '2026-08-23 09:00:00',
            'input_tokens' => self::OVER_THE_CAP,
            'output_tokens' => 0,
            'reasoning_tokens' => 0,
        ]);

        $this->suggest()->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_a_cap_of_zero_means_no_cap(): void
    {
        $this->budget(daily: self::NO_CAP, monthly: self::NO_CAP);
        $this->spend(['created_at' => '2026-08-23 09:00:00', 'input_tokens' => self::OVER_THE_CAP]);

        $this->suggest()->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function suggest(): TestResponse
    {
        return $this->postJson('/api/v1/ai/suggest', ['target' => 'north_star_metric']);
    }

    private function budget(int $daily, int $monthly): void
    {
        Setting::factory()->forAccount((int) $this->account->id)
            ->create(['key' => 'ai.budget.daily_tokens', 'value' => $daily]);
        Setting::factory()->forAccount((int) $this->account->id)
            ->create(['key' => 'ai.budget.monthly_tokens', 'value' => $monthly]);
    }

    private function spend(array $attributes): void
    {
        LlmUsageLog::factory()->create([
            'input_tokens' => 0,
            'output_tokens' => 0,
            'reasoning_tokens' => 0,
            ...$attributes,
            'account_id' => $this->account->id,
        ]);
    }
}
