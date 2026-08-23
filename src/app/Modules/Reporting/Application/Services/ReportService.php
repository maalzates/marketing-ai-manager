<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Services;

use App\Modules\Ai\Application\DTO\AiRequestDTO;
use App\Modules\Ai\Application\Services\AiService;
use App\Modules\Ai\Domain\Enums\AiTask;
use App\Modules\Experiments\Application\DTO\ExperimentFilterDTO;
use App\Modules\Experiments\Application\DTO\SuggestedVerdictDTO;
use App\Modules\Experiments\Application\Services\ExperimentService;
use App\Modules\Experiments\Application\Services\VerdictService;
use App\Modules\Experiments\Domain\Enums\ExperimentStatus;
use App\Modules\Experiments\Domain\ValueObjects\MetricTotals;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use App\Modules\Reporting\Application\DTO\CreateReportDTO;
use App\Modules\Reporting\Application\DTO\ReportFilterDTO;
use App\Modules\Reporting\Domain\Contracts\ReportRepositoryInterface;
use App\Modules\Reporting\Domain\Enums\ReportType;
use App\Modules\Reporting\Domain\Exceptions\ReportNotFoundException;
use App\Modules\Reporting\Infrastructure\Persistence\Report;
use App\Modules\Settings\Application\Services\SettingsService;
use App\Modules\Strategies\Application\DTO\StrategyFilterDTO;
use App\Modules\Strategies\Application\Services\StrategyService;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * The narrative around numbers somebody else computed. Every arithmetic claim in a report
 * comes from VerdictService or MetricTotals; the model only turns it into prose, which is
 * why a report can never disagree with the verdict it explains.
 */
readonly class ReportService
{
    private const string REPORTS_ENABLED_SETTING = 'guardian.reports_enabled';

    private const string FREQUENCY_SETTING = 'guardian.frequency_days';

    private const string VERDICT_PROMPT = 'Redacta en español el informe de cierre de un experimento de marketing, '
        .'para alguien sin formación en Meta Ads. Estructura: qué se probó, qué se esperaba, qué ocurrió, por qué el '
        .'veredicto sugerido es "%s", y qué conviene probar después. El veredicto y sus cifras ya están calculados en '
        .'el contexto: explícalos con tus palabras, no los recalcules ni los contradigas, y no inventes ninguna cifra '
        .'que no esté ahí.';

    private const string PERIODIC_PROMPT = 'Redacta en español un informe periódico breve sobre los experimentos '
        .'activos de esta estrategia entre %s y %s, para alguien sin formación en Meta Ads. Usa únicamente las cifras '
        .'del contexto. Di qué está funcionando, qué no, y qué merece atención. Si no hay nada relevante que señalar, '
        .'dilo en una frase en lugar de rellenar.';

    public function __construct(
        private ReportRepositoryInterface $repository,
        private ExperimentService $experiments,
        private VerdictService $verdicts,
        private StrategyService $strategies,
        private SettingsService $settings,
        private AiService $ai,
    ) {}

    /**
     * @return Collection<int, Report>|LengthAwarePaginator<int, Report>
     */
    public function list(ReportFilterDTO $filters): Collection|LengthAwarePaginator
    {
        return $this->repository->findAll($filters);
    }

    public function find(int $id, int $accountId): Report
    {
        return $this->repository->findById($id, $accountId) ?? throw ReportNotFoundException::withId($id);
    }

    /**
     * The existing-report read happens before anything else, so a job re-run on the same day
     * returns the report it already paid for instead of buying a second opinion.
     */
    public function generateForExperiment(int $experimentId, int $accountId): Report
    {
        return $this->repository->findVerdictReport($experimentId, $accountId)
            ?? $this->writeVerdictReport(
                $this->experiments->find($experimentId, $accountId),
                $this->verdicts->suggest($experimentId, $accountId),
            );
    }

    /**
     * Every experiment of the account whose story is over and whose report was never written:
     * expired, or closed early by an accepted guardián proposal. `generateForExperiment()`
     * short-circuits on the ones already reported, so a second run the same day writes nothing
     * and calls no model.
     *
     * @return Collection<int, Report>
     */
    public function generateDueVerdictReports(int $accountId): Collection
    {
        return $this->strategies
            ->forAccount(new StrategyFilterDTO($accountId))
            ->flatMap(fn (Strategy $strategy): Collection => $this->concludedExperimentsOf(
                (int) $strategy->id,
                $accountId,
            ))
            ->map(fn (Experiment $experiment): Report => $this->generateForExperiment(
                (int) $experiment->id,
                $accountId,
            ))
            ->values();
    }

    /**
     * Null is an outcome, not a failure: the periodic report is switched off for this
     * strategy, the period is already covered, or there is nothing active to report on.
     */
    public function generatePeriodic(
        int $strategyId,
        int $accountId,
        ?CarbonImmutable $periodStart = null,
        ?CarbonImmutable $periodEnd = null,
    ): ?Report {
        // First, so a strategy of another account is a 404 before its settings are read.
        $strategy = $this->strategies->find($strategyId, $accountId);
        $end = $periodEnd ?? CarbonImmutable::now()->startOfDay();
        $start = $periodStart ?? $end->subDays($this->frequencyDays($strategyId, $accountId) - 1);

        if (! $this->settings->get(self::REPORTS_ENABLED_SETTING, $accountId, $strategyId)) {
            return null;
        }

        if ($this->repository->findPeriodicReport($strategyId, $accountId, $start, $end) !== null) {
            return null;
        }

        $active = $this->activeExperimentsOf($strategyId, $accountId);

        return $active->isEmpty()
            ? null
            : $this->writePeriodicReport($strategy, $active, $start, $end);
    }

    private function writeVerdictReport(Experiment $experiment, SuggestedVerdictDTO $suggested): Report
    {
        $data = [
            'experiment' => $this->summarise($experiment),
            'expected_result' => $suggested->expected->toArray(),
            'suggested_verdict' => $suggested->verdict->value,
            'verdict_reasoning' => $suggested->reasoning,
            'actual_value' => $suggested->actualValue,
            'days_with_data' => $suggested->daysWithData,
        ];

        return $this->repository->create(new CreateReportDTO(
            (int) $experiment->account_id,
            ReportType::ExperimentVerdict,
            $this->narrative(
                (int) $experiment->account_id,
                (int) $experiment->strategy_id,
                AiTask::Verdict,
                sprintf(self::VERDICT_PROMPT, $suggested->verdict->value),
                $data,
                $suggested->reasoning,
            ),
            $data,
            CarbonImmutable::now(),
            (int) $experiment->strategy_id,
            (int) $experiment->id,
        ));
    }

    /**
     * @param  Collection<int, Experiment>  $active
     */
    private function writePeriodicReport(
        Strategy $strategy,
        Collection $active,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): Report {
        $data = [
            'strategy' => [
                'id' => (int) $strategy->id,
                'name' => $strategy->name,
                'objective' => $strategy->objective,
                'north_star_metric' => $strategy->north_star_metric,
                'monthly_budget' => $strategy->monthly_budget === null ? null : (float) $strategy->monthly_budget,
            ],
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'experiments' => $active
                ->map(fn (Experiment $experiment): array => $this->summarise($experiment))
                ->values()
                ->all(),
        ];

        return $this->repository->create(new CreateReportDTO(
            (int) $strategy->account_id,
            ReportType::Periodic,
            $this->narrative(
                (int) $strategy->account_id,
                (int) $strategy->id,
                AiTask::Guardian,
                sprintf(self::PERIODIC_PROMPT, $start->toDateString(), $end->toDateString()),
                $data,
                'No hay narrativa disponible para este periodo; los datos del informe siguen siendo válidos.',
            ),
            $data,
            CarbonImmutable::now(),
            (int) $strategy->id,
            periodStart: $start,
            periodEnd: $end,
        ));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function narrative(
        int $accountId,
        int $strategyId,
        AiTask $task,
        string $prompt,
        array $context,
        string $fallback,
    ): string {
        return $this->ai->complete(new AiRequestDTO(
            $accountId,
            $task,
            $prompt,
            $context,
            strategyId: $strategyId,
        ))->text ?? $fallback;
    }

    /**
     * @return array<string, mixed>
     */
    private function summarise(Experiment $experiment): array
    {
        $totals = MetricTotals::fromDaily(
            $this->experiments->metricsFor((int) $experiment->id, (int) $experiment->account_id),
        );

        return [
            'id' => (int) $experiment->id,
            'code' => $experiment->code,
            'title' => $experiment->title,
            'type' => $experiment->type->value,
            'platform' => $experiment->platform->value,
            'hypothesis' => $experiment->hypothesis,
            'expected_result' => $experiment->expected_result,
            'starts_at' => $experiment->starts_at?->toDateString(),
            'ends_at' => $experiment->ends_at?->toDateString(),
            'max_budget' => $experiment->max_budget === null ? null : (float) $experiment->max_budget,
            'days_with_data' => $totals->days,
            'spend' => $totals->spend,
            'impressions' => $totals->impressions,
            'reach' => $totals->reach,
            'clicks' => $totals->clicks,
            'conversions' => $totals->conversions,
            'ctr' => $totals->valueOf('ctr'),
            'cpm' => $totals->valueOf('cpm'),
            'cpa' => $totals->valueOf('cpa'),
        ];
    }

    /**
     * Running past its end date, or already closed early. Either way the experiment is over
     * and owes a verdict; anything still draft, scheduled or cancelled never will.
     *
     * @return Collection<int, Experiment>
     */
    private function concludedExperimentsOf(int $strategyId, int $accountId): Collection
    {
        return $this->experiments
            ->forStrategy(new ExperimentFilterDTO($accountId, $strategyId, null, null, null, 0, 1))
            ->filter(fn (Experiment $experiment): bool => $this->hasConcluded($experiment))
            ->values();
    }

    private function hasConcluded(Experiment $experiment): bool
    {
        return match ($experiment->status) {
            ExperimentStatus::Completed => true,
            ExperimentStatus::Running => $experiment->ends_at !== null && $experiment->ends_at->isPast(),
            default => false,
        };
    }

    /**
     * @return Collection<int, Experiment>
     */
    private function activeExperimentsOf(int $strategyId, int $accountId): Collection
    {
        return $this->experiments->forStrategy(new ExperimentFilterDTO(
            $accountId,
            $strategyId,
            ExperimentStatus::Running,
            null,
            null,
            0,
            1,
        ));
    }

    private function frequencyDays(int $strategyId, int $accountId): int
    {
        return max(1, (int) $this->settings->get(self::FREQUENCY_SETTING, $accountId, $strategyId));
    }
}
