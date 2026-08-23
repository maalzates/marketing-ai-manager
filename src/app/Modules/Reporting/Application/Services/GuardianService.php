<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Services;

use App\Modules\Ai\Application\DTO\AiRequestDTO;
use App\Modules\Ai\Application\Services\AiService;
use App\Modules\Ai\Domain\Enums\AiTask;
use App\Modules\Audit\Application\DTO\ActionLogFilterDTO;
use App\Modules\Audit\Application\DTO\RecordActionDTO;
use App\Modules\Audit\Application\Services\ActionLogService;
use App\Modules\Audit\Domain\Enums\ActionOrigin;
use App\Modules\Audit\Infrastructure\Persistence\ActionLog;
use App\Modules\Campaigns\Application\DTO\SyncCampaignMetricsDTO;
use App\Modules\Campaigns\Application\Services\CampaignMetricsSyncService;
use App\Modules\Content\Application\Services\OwnMetricsImportService;
use App\Modules\Experiments\Application\DTO\ExperimentFilterDTO;
use App\Modules\Experiments\Application\Services\ExperimentService;
use App\Modules\Experiments\Application\Services\LearningPhaseService;
use App\Modules\Experiments\Application\Services\VerdictService;
use App\Modules\Experiments\Domain\Enums\ExperimentStatus;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use App\Modules\Proposals\Application\DTO\ProposalFilterDTO;
use App\Modules\Proposals\Application\DTO\ProposeDTO;
use App\Modules\Proposals\Application\Services\ProposalService;
use App\Modules\Proposals\Domain\Enums\ProposalOrigin;
use App\Modules\Proposals\Domain\Enums\ProposalStatus;
use App\Modules\Proposals\Domain\Enums\ProposalType;
use App\Modules\Proposals\Infrastructure\Persistence\Proposal;
use App\Modules\Reporting\Domain\ValueObjects\AnomalyFinding;
use App\Modules\Settings\Application\Services\SettingsService;
use App\Modules\Strategies\Application\DTO\StrategyFilterDTO;
use App\Modules\Strategies\Application\Services\StrategyService;
use App\Modules\Strategies\Domain\Enums\StrategyStatus;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * A guardián that speaks every day is a guardián nobody reads, so this one returns an empty
 * collection almost always and that is the feature. Three things buy that silence: the
 * auto-skip below, detection that is pure arithmetic (AnomalyDetector), and the learning
 * phase, during which early performance is volatile by design and judging it is judging noise.
 */
readonly class GuardianService
{
    private const string ENABLED_SETTING = 'guardian.enabled';

    private const string FREQUENCY_SETTING = 'guardian.frequency_days';

    private const string MULTIPLIER_SETTING = 'guardian.anomaly_multiplier';

    private const string RUN_ACTION = 'guardian.run';

    private const string ENTITY_TYPE = 'strategy';

    private const string PROPOSAL_PROMPT = 'El guardián detectó estas anomalías en un experimento de marketing. '
        .'Escribe en español, en dos o tres frases y sin tecnicismos, por qué conviene cerrar el experimento ahora. '
        .'Apóyate solo en las cifras del contexto: no inventes ninguna, no propongas alternativas y no saludes.';

    public function __construct(
        private StrategyService $strategies,
        private ExperimentService $experiments,
        private CampaignMetricsSyncService $adsMetrics,
        private OwnMetricsImportService $organicMetrics,
        private VerdictService $verdicts,
        private LearningPhaseService $learningPhase,
        private AnomalyDetector $detector,
        private ProposalService $proposals,
        private SettingsService $settings,
        private ActionLogService $actionLog,
        private AiService $ai,
    ) {}

    /**
     * The strategies whose guardián is due today: enabled, active, and not already run inside
     * their own frequency window. Reading the action log instead of a `last_run_at` column is
     * what makes a second dispatch on the same day a no-op.
     *
     * @return Collection<int, Strategy>
     */
    public function dueStrategies(int $accountId): Collection
    {
        return $this->strategies
            ->forAccount(new StrategyFilterDTO($accountId, StrategyStatus::Active))
            ->filter(fn (Strategy $strategy): bool => $this->isDue($strategy, $accountId))
            ->values();
    }

    /**
     * @return Collection<int, Proposal> the proposals raised; empty means all is well
     */
    public function runForStrategy(int $strategyId, int $accountId): Collection
    {
        // Ownership guard: a strategy of another account is a 404 before anything is read.
        $this->strategies->find($strategyId, $accountId);

        // Auto-skip: no active experiments means no sync, no prompt and no proposal — the
        // whole run costs one indexed query. It reactivates by itself when one starts.
        $active = $this->activeExperimentsOf($strategyId, $accountId);

        if ($active->isEmpty()) {
            return collect();
        }

        $this->recordRun($strategyId, $accountId, $active->count());

        return $active
            ->map(fn (Experiment $experiment): ?Proposal => $this->inspect($experiment, $accountId))
            ->filter()
            ->values();
    }

    private function inspect(Experiment $experiment, int $accountId): ?Proposal
    {
        $this->refreshMetrics($experiment, $accountId);

        $findings = $this->actionable($experiment, $this->detector->detect(
            $experiment,
            $this->experiments->metricsFor((int) $experiment->id, $accountId),
            (float) $this->settings->get(self::MULTIPLIER_SETTING, $accountId, (int) $experiment->strategy_id),
        ));

        return $findings->isEmpty() || $this->hasPendingProposal($experiment, $accountId)
            ? null
            : $this->propose($experiment, $accountId, $findings);
    }

    /**
     * Synchronously, not as queued jobs: detection reads the rows these two write, and a
     * dispatch would have it judge yesterday's numbers. Both answer null — and make no
     * outbound call — for an experiment that is not theirs, so neither needs a type branch.
     */
    private function refreshMetrics(Experiment $experiment, int $accountId): void
    {
        $this->adsMetrics->sync(new SyncCampaignMetricsDTO($accountId, (int) $experiment->id));

        $this->organicMetrics->importForExperiment($accountId, (int) $experiment->id);
    }

    /**
     * The learning-phase branch, and the only place it is taken. Inside Meta's window an ads
     * experiment may only be reported for evident disasters — money leaving with nothing
     * coming back — never for early performance, which is volatile by design (core.md §10.6).
     *
     * @param  Collection<int, AnomalyFinding>  $findings
     * @return Collection<int, AnomalyFinding>
     */
    private function actionable(Experiment $experiment, Collection $findings): Collection
    {
        return $this->learningPhase->isWithinLearningWindow($experiment, CarbonImmutable::now())
            ? $findings->filter(fn (AnomalyFinding $finding): bool => $finding->kind->isEvidentDisaster())->values()
            : $findings;
    }

    /**
     * @param  Collection<int, AnomalyFinding>  $findings
     */
    private function propose(Experiment $experiment, int $accountId, Collection $findings): Proposal
    {
        return $this->proposals->propose(new ProposeDTO(
            $accountId,
            null,
            ProposalType::CloseExperiment,
            ProposalOrigin::Guardian,
            sprintf('Cerrar %s: %s', $experiment->code, $findings->first()->kind->label()),
            $this->rationale($experiment, $accountId, $findings),
            [
                // The same arithmetic the user will see confirmed at close time, not a second opinion.
                'verdict' => $this->verdicts->suggest((int) $experiment->id, $accountId)->verdict->value,
                'reason' => $this->headline($findings),
                'anomalies' => $findings->map(fn (AnomalyFinding $f): array => $f->toArray())->all(),
                'within_learning_window' => $this->learningPhase
                    ->isWithinLearningWindow($experiment, CarbonImmutable::now()),
            ],
            (int) $experiment->strategy_id,
            (int) $experiment->id,
            $this->nextRunAfter($accountId, (int) $experiment->strategy_id),
        ));
    }

    /**
     * @param  Collection<int, AnomalyFinding>  $findings
     */
    private function rationale(Experiment $experiment, int $accountId, Collection $findings): string
    {
        return $this->ai->complete(new AiRequestDTO(
            $accountId,
            AiTask::Guardian,
            self::PROPOSAL_PROMPT,
            [
                'experiment' => ['id' => (int) $experiment->id, 'code' => $experiment->code, 'title' => $experiment->title],
                'anomalies' => $findings->map(fn (AnomalyFinding $f): array => $f->toArray())->all(),
            ],
            strategyId: (int) $experiment->strategy_id,
        ))->text ?? $this->headline($findings);
    }

    /**
     * @param  Collection<int, AnomalyFinding>  $findings
     */
    private function headline(Collection $findings): string
    {
        return $findings->map(fn (AnomalyFinding $finding): string => $finding->summary)->implode(' ');
    }

    /**
     * One open proposal per experiment, so a second run today cannot queue a second button.
     * An expired one does not count: its numbers are from a previous window, and closing an
     * experiment on evidence nobody re-checked is worse than asking again with today's.
     */
    private function hasPendingProposal(Experiment $experiment, int $accountId): bool
    {
        return $this->proposals->list(new ProposalFilterDTO(
            $accountId,
            ProposalStatus::Pending,
            ProposalType::CloseExperiment,
            ProposalOrigin::Guardian,
            (int) $experiment->strategy_id,
            (int) $experiment->id,
            0,
            1,
        ))->reject(fn (Proposal $proposal): bool => $proposal->hasExpired())->isNotEmpty();
    }

    /** A guardián proposal is only as good as the run that raised it, so it lasts exactly that long. */
    private function nextRunAfter(int $accountId, int $strategyId): CarbonImmutable
    {
        return CarbonImmutable::now()->addDays($this->frequencyDays($accountId, $strategyId));
    }

    private function isDue(Strategy $strategy, int $accountId): bool
    {
        return (bool) $this->settings->get(self::ENABLED_SETTING, $accountId, (int) $strategy->id)
            && ! $this->hasRunSince($strategy, $accountId, CarbonImmutable::now()->subDays(
                $this->frequencyDays($accountId, (int) $strategy->id),
            ));
    }

    private function frequencyDays(int $accountId, int $strategyId): int
    {
        return max(1, (int) $this->settings->get(self::FREQUENCY_SETTING, $accountId, $strategyId));
    }

    private function hasRunSince(Strategy $strategy, int $accountId, CarbonImmutable $since): bool
    {
        return $this->actionLog
            ->findAll(ActionLogFilterDTO::forAccount($accountId, self::RUN_ACTION, ActionOrigin::JOB, $since, null, 0, 1))
            ->contains(fn (ActionLog $log): bool => (int) $log->entity_id === (int) $strategy->id);
    }

    private function recordRun(int $strategyId, int $accountId, int $activeExperiments): void
    {
        $this->actionLog->record(new RecordActionDTO(
            $accountId,
            null,
            self::RUN_ACTION,
            ActionOrigin::JOB,
            ['active_experiments' => $activeExperiments],
            self::ENTITY_TYPE,
            $strategyId,
        ));
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
}
