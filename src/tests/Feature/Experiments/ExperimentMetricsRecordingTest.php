<?php

declare(strict_types=1);

namespace Tests\Feature\Experiments;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Experiments\Application\DTO\RecordMetricsDTO;
use App\Modules\Experiments\Application\Services\ExperimentService;
use App\Modules\Experiments\Domain\Exceptions\ExperimentNotFoundException;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A provider re-reports the same day as often as it likes — a nightly sync run twice must
 * leave one row for that day and a spend total that still matches reality. The importers
 * in Campaigns and Content are the callers; there is no HTTP door, so the Service is
 * driven the way they drive it.
 */
class ExperimentMetricsRecordingTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private Experiment $experiment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-10 06:00:00'));

        $user = User::factory()->create();
        $this->account = Account::factory()->create(['owner_user_id' => $user->id]);
        $user->accounts()->attach($this->account);
        $this->experiment = Experiment::factory()->running()->create([
            'account_id' => $this->account->id,
            'strategy_id' => Strategy::factory()->create(['account_id' => $this->account->id]),
            'spend_total' => 0,
        ]);

        Sanctum::actingAs($user);
    }

    public function test_recording_the_same_day_twice_leaves_one_row(): void
    {
        $this->record(CarbonImmutable::parse('2026-09-05'), 50.0);
        $this->record(CarbonImmutable::parse('2026-09-05'), 80.0);

        $this->assertDatabaseCount('experiment_metrics', 1);
        $this->assertDatabaseHas('experiment_metrics', [
            'experiment_id' => $this->experiment->id,
            'date' => '2026-09-05',
            'spend' => '80.00',
        ]);
    }

    public function test_the_spend_total_is_recomputed_instead_of_accumulated(): void
    {
        $this->record(CarbonImmutable::parse('2026-09-05'), 50.0);
        $this->record(CarbonImmutable::parse('2026-09-05'), 80.0);

        $this->assertSame('80.00', $this->experiment->refresh()->spend_total);
    }

    public function test_the_spend_total_adds_up_every_recorded_day(): void
    {
        $this->record(CarbonImmutable::parse('2026-09-05'), 50.0);
        $this->record(CarbonImmutable::parse('2026-09-06'), 20.5);

        $this->assertSame('70.50', $this->experiment->refresh()->spend_total);
    }

    public function test_the_metrics_endpoint_returns_the_daily_series_oldest_first(): void
    {
        $this->record(CarbonImmutable::parse('2026-09-06'), 20.0);
        $this->record(CarbonImmutable::parse('2026-09-05'), 50.0);

        $response = $this->getJson("/api/v1/experiments/{$this->experiment->id}/metrics")->assertOk();

        $this->assertSame(['2026-09-05', '2026-09-06'], array_map(
            static fn (array $day): string => substr((string) $day['date'], 0, 10),
            $response->json('result'),
        ));
    }

    public function test_recording_against_another_accounts_experiment_is_a_not_found(): void
    {
        $foreign = Experiment::factory()->create();

        $this->expectException(ExperimentNotFoundException::class);

        $this->app->make(ExperimentService::class)->recordMetrics(new RecordMetricsDTO(
            (int) $this->account->id,
            (int) $foreign->id,
            CarbonImmutable::parse('2026-09-05'),
            50.0,
            10000,
            8000,
            150,
            1.5,
            5.0,
            0.33,
            5,
            10.0,
            1.25,
            2000,
            300,
        ));
    }

    public function test_no_metric_row_is_written_for_another_accounts_experiment(): void
    {
        $foreign = Experiment::factory()->create();

        try {
            $this->app->make(ExperimentService::class)->recordMetrics(new RecordMetricsDTO(
                (int) $this->account->id,
                (int) $foreign->id,
                CarbonImmutable::parse('2026-09-05'),
                50.0,
                10000,
                8000,
                150,
                1.5,
                5.0,
                0.33,
                5,
                10.0,
                1.25,
                2000,
                300,
            ));
        } catch (ExperimentNotFoundException) {
            // The assertion below is the behaviour; the exception is only how it surfaces.
        }

        $this->assertDatabaseCount('experiment_metrics', 0);
    }

    private function record(CarbonImmutable $date, float $spend): void
    {
        $this->app->make(ExperimentService::class)->recordMetrics(new RecordMetricsDTO(
            (int) $this->account->id,
            (int) $this->experiment->id,
            $date,
            $spend,
            10000,
            8000,
            150,
            1.5,
            5.0,
            0.33,
            5,
            10.0,
            1.25,
            2000,
            300,
        ));
    }
}
