<?php

declare(strict_types=1);

namespace Tests\Feature\Experiments;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Experiments\Application\DTO\CreateExperimentDTO;
use App\Modules\Experiments\Application\Services\ExperimentService;
use App\Modules\Experiments\Domain\Enums\ExperimentPlatform;
use App\Modules\Experiments\Domain\Enums\ExperimentStatus;
use App\Modules\Experiments\Domain\Enums\ExperimentType;
use App\Modules\Experiments\Domain\Exceptions\ExperimentDurationTooShortException;
use App\Modules\Experiments\Domain\Exceptions\ExperimentWithoutExpectedResultException;
use App\Modules\Strategies\Domain\Exceptions\StrategyArchivedException;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Carbon\CarbonImmutable;
use Database\Seeders\DomainKnowledgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * "No experiment without an expected result and an end date" is a domain rule, not a
 * FormRequest rule: the request declares both fields `nullable` on purpose. Every rule
 * below is therefore checked twice — once through HTTP, once through the Service as a chat
 * tool or a job would call it. A rule only the HTTP door enforces protects one door in three.
 */
class ExperimentInvariantsTest extends TestCase
{
    use RefreshDatabase;

    private const string MISSING_CONTRACT_MESSAGE = 'Un experimento necesita un resultado esperado '
        .'({metric, operator, value}) y una fecha de fin. Sin eso no es un experimento, '
        .'es una campaña a ciegas.';

    private Account $account;

    private Strategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-01 09:00:00'));
        $this->seed(DomainKnowledgeSeeder::class);

        $user = User::factory()->create();
        $this->account = Account::factory()->create(['owner_user_id' => $user->id]);
        $user->accounts()->attach($this->account);
        $this->strategy = Strategy::factory()->create([
            'account_id' => $this->account->id,
            'monthly_budget' => 2000,
        ]);

        Sanctum::actingAs($user);
    }

    public function test_rejects_an_experiment_without_an_expected_result(): void
    {
        $this->postJson($this->storeUrl(), $this->payload(['expected_result' => null]))
            ->assertStatus(422)
            ->assertJsonPath('errors.message', self::MISSING_CONTRACT_MESSAGE);

        $this->assertDatabaseCount('experiments', 0);
    }

    public function test_rejects_an_experiment_without_an_expected_result_outside_http(): void
    {
        $this->expectException(ExperimentWithoutExpectedResultException::class);
        $this->expectExceptionMessage(self::MISSING_CONTRACT_MESSAGE);

        $this->app->make(ExperimentService::class)->create($this->dto(['expected_result' => null]));
    }

    public function test_writes_no_experiment_when_the_expected_result_is_missing_outside_http(): void
    {
        try {
            $this->app->make(ExperimentService::class)->create($this->dto(['expected_result' => null]));
        } catch (ExperimentWithoutExpectedResultException) {
            // The assertion below is the behaviour; the exception is only how it surfaces.
        }

        $this->assertDatabaseCount('experiments', 0);
    }

    public function test_rejects_an_experiment_without_an_end_date(): void
    {
        $this->postJson($this->storeUrl(), $this->payload(['ends_at' => null]))
            ->assertStatus(422)
            ->assertJsonPath('errors.message', self::MISSING_CONTRACT_MESSAGE);

        $this->assertDatabaseCount('experiments', 0);
    }

    public function test_rejects_an_experiment_without_an_end_date_outside_http(): void
    {
        $this->expectException(ExperimentWithoutExpectedResultException::class);

        $this->app->make(ExperimentService::class)->create($this->dto(['ends_at' => null]));
    }

    public function test_rejects_an_expected_result_without_a_metric(): void
    {
        $this->postJson($this->storeUrl(), $this->payload([
            'expected_result' => ['operator' => 'lte', 'value' => 20],
        ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.message', self::MISSING_CONTRACT_MESSAGE);
    }

    public function test_rejects_an_expected_result_without_an_operator(): void
    {
        $this->postJson($this->storeUrl(), $this->payload([
            'expected_result' => ['metric' => 'cpa', 'value' => 20],
        ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.message', self::MISSING_CONTRACT_MESSAGE);
    }

    public function test_rejects_an_expected_result_without_a_value(): void
    {
        $this->postJson($this->storeUrl(), $this->payload([
            'expected_result' => ['metric' => 'cpa', 'operator' => 'lte'],
        ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.message', self::MISSING_CONTRACT_MESSAGE);
    }

    public function test_rejects_an_ads_experiment_shorter_than_seven_days(): void
    {
        $this->postJson($this->storeUrl(), $this->payload(['ends_at' => '2026-09-07']))
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.message',
                'Un experimento de ads dura al menos 7 días y este dura 6. Evaluar antes es evaluar ruido.',
            );

        $this->assertDatabaseCount('experiments', 0);
    }

    public function test_accepts_an_ads_experiment_of_exactly_seven_days(): void
    {
        $this->postJson($this->storeUrl(), $this->payload(['ends_at' => '2026-09-08']))->assertCreated();

        $this->assertDatabaseHas('experiments', ['strategy_id' => $this->strategy->id, 'code' => 'EXP-001']);
    }

    public function test_rejects_a_short_ads_experiment_outside_http(): void
    {
        $this->expectException(ExperimentDurationTooShortException::class);

        $this->app->make(ExperimentService::class)->create(
            $this->dto(['ends_at' => CarbonImmutable::parse('2026-09-05')]),
        );
    }

    public function test_lets_an_organic_experiment_run_for_less_than_seven_days(): void
    {
        $this->postJson($this->storeUrl(), $this->payload([
            'type' => 'organic',
            'ends_at' => '2026-09-03',
            'max_budget' => null,
            'expected_result' => ['metric' => 'engagement_rate', 'operator' => 'gte', 'value' => 3],
        ]))->assertCreated();
    }

    public function test_rejects_an_experiment_under_an_archived_strategy(): void
    {
        $archived = Strategy::factory()->archived()->create([
            'account_id' => $this->account->id,
            'monthly_budget' => 2000,
        ]);

        $this->postJson("/api/v1/strategies/{$archived->id}/experiments", $this->payload())
            ->assertStatus(409)
            ->assertJsonPath('errors.message', 'This strategy is archived. Activate it before making any change to it.');

        $this->assertDatabaseCount('experiments', 0);
    }

    public function test_rejects_an_experiment_under_an_archived_strategy_outside_http(): void
    {
        $archived = Strategy::factory()->archived()->create([
            'account_id' => $this->account->id,
            'monthly_budget' => 2000,
        ]);

        $this->expectException(StrategyArchivedException::class);

        $this->app->make(ExperimentService::class)->create($this->dto(['strategy_id' => (int) $archived->id]));
    }

    private function storeUrl(): string
    {
        return "/api/v1/strategies/{$this->strategy->id}/experiments";
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
            'max_budget' => 300,
            'status' => 'draft',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function dto(array $overrides = []): CreateExperimentDTO
    {
        $attributes = [
            'strategy_id' => (int) $this->strategy->id,
            'expected_result' => ['metric' => 'cpa', 'operator' => 'lte', 'value' => 20],
            'ends_at' => CarbonImmutable::parse('2026-09-15'),
            ...$overrides,
        ];

        return new CreateExperimentDTO(
            (int) $this->account->id,
            $attributes['strategy_id'],
            ExperimentType::Ads,
            ExperimentPlatform::Instagram,
            'Desde un job',
            'Saltarse el FormRequest.',
            $attributes['expected_result'],
            CarbonImmutable::parse('2026-09-01'),
            $attributes['ends_at'],
            300.0,
            [],
            ExperimentStatus::Draft,
        );
    }
}
