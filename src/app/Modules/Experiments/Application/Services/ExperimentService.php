<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Application\Services;

use App\Modules\Audit\Application\DTO\RecordActionDTO;
use App\Modules\Audit\Application\Services\ActionLogService;
use App\Modules\Audit\Domain\Enums\ActionOrigin;
use App\Modules\Experiments\Application\DTO\CreateExperimentDTO;
use App\Modules\Experiments\Application\DTO\ExperimentFilterDTO;
use App\Modules\Experiments\Application\DTO\RecordMetricsDTO;
use App\Modules\Experiments\Application\DTO\UpdateExperimentDTO;
use App\Modules\Experiments\Domain\Contracts\ExperimentMetricRepositoryInterface;
use App\Modules\Experiments\Domain\Contracts\ExperimentRepositoryInterface;
use App\Modules\Experiments\Domain\Contracts\ExperimentWarningRepositoryInterface;
use App\Modules\Experiments\Domain\Enums\ExperimentStatus;
use App\Modules\Experiments\Domain\Enums\ExperimentType;
use App\Modules\Experiments\Domain\Enums\LearningResettingChange;
use App\Modules\Experiments\Domain\Exceptions\ExperimentBudgetExceedsCapException;
use App\Modules\Experiments\Domain\Exceptions\ExperimentBudgetNotVerifiableException;
use App\Modules\Experiments\Domain\Exceptions\ExperimentDurationTooShortException;
use App\Modules\Experiments\Domain\Exceptions\ExperimentNotFoundException;
use App\Modules\Experiments\Domain\Exceptions\ExperimentWithoutExpectedResultException;
use App\Modules\Experiments\Domain\ValueObjects\ExpectedResult;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use App\Modules\Experiments\Infrastructure\Persistence\ExperimentMetric;
use App\Modules\Experiments\Infrastructure\Persistence\ExperimentWarning;
use App\Modules\Settings\Application\Services\SettingsService;
use App\Modules\Strategies\Application\Services\StrategyService;
use App\Modules\Strategies\Domain\Enums\StrategyStatus;
use App\Modules\Strategies\Domain\Exceptions\StrategyArchivedException;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * The four rules that make an experiment an experiment live here and nowhere else: HTTP,
 * chat and jobs all arrive through these methods, so a door cannot skip one.
 */
readonly class ExperimentService
{
    private const string ENTITY_TYPE = 'experiment';

    private const string MAX_BUDGET_SETTING = 'budgets.max_per_experiment';

    public function __construct(
        private ExperimentRepositoryInterface $repository,
        private ExperimentMetricRepositoryInterface $metrics,
        private ExperimentWarningRepositoryInterface $warnings,
        private ExperimentWarningGenerator $warningGenerator,
        private LearningPhaseService $learningPhase,
        private MetaAdsRuleService $rules,
        private StrategyService $strategies,
        private SettingsService $settings,
        private ActionLogService $actionLog,
    ) {}

    /**
     * @return Collection<int, Experiment>|LengthAwarePaginator<int, Experiment>
     */
    public function forStrategy(ExperimentFilterDTO $filters): Collection|LengthAwarePaginator
    {
        return $this->repository->findAll($filters);
    }

    /** An experiment of another account is a 404, never a 403: a 403 confirms the id exists. */
    public function find(int $id, int $accountId): Experiment
    {
        return $this->repository->findById($id, $accountId) ?? throw ExperimentNotFoundException::withId($id);
    }

    /**
     * @return Collection<int, ExperimentMetric>
     */
    public function metricsFor(int $experimentId, int $accountId): Collection
    {
        $this->find($experimentId, $accountId);

        return $this->metrics->findForExperiment($experimentId, $accountId);
    }

    /**
     * @return Collection<int, ExperimentWarning>
     */
    public function warningsFor(int $experimentId, int $accountId): Collection
    {
        $this->find($experimentId, $accountId);

        return $this->warnings->findForExperiment($experimentId, $accountId);
    }

    public function create(CreateExperimentDTO $dto): Experiment
    {
        // StrategyService::find() doubles as the ownership guard: another account's strategy is a 404 here.
        $strategy = $this->strategies->find($dto->strategyId, $dto->accountId);

        $this->assertStrategyAcceptsExperiments($strategy);
        $this->assertExpectedResultIsExplicit($dto->strategyId, $dto->expectedResult, $dto->endsAt);
        $this->assertDurationIsSufficient($dto->type, $dto->startsAt, $dto->endsAt);
        $this->assertBudgetWithinCaps($strategy, $dto->type, $dto->accountId, $dto->maxBudget, $dto->startsAt, null);

        return $this->withGeneratedWarnings($this->repository->create(
            $dto,
            $this->repository->nextCode($dto->accountId),
            $dto->type === ExperimentType::Ads ? $this->learningPhase->endsAt($dto->startsAt) : null,
        ));
    }

    public function update(UpdateExperimentDTO $dto): Experiment
    {
        $experiment = $this->find($dto->experimentId, $dto->accountId);

        $this->assertExpectedResultIsExplicit(
            (int) $experiment->strategy_id,
            $dto->expectedResult ?? $experiment->expected_result,
            $dto->endsAt ?? $experiment->ends_at,
        );
        $this->assertDurationIsSufficient(
            $experiment->type,
            $dto->startsAt ?? $experiment->starts_at,
            $dto->endsAt ?? $experiment->ends_at,
        );
        $this->assertBudgetWithinCaps(
            $this->strategies->find((int) $experiment->strategy_id, $dto->accountId),
            $experiment->type,
            $dto->accountId,
            $dto->maxBudget ?? $this->currentMaxBudget($experiment),
            $dto->startsAt ?? $experiment->starts_at,
            (int) $experiment->id,
        );

        return $this->repository->update($experiment, $dto, $this->learningPhaseResetFor($experiment, $dto));
    }

    /** Re-syncing a day overwrites it; the running total is derived, never accumulated. */
    public function recordMetrics(RecordMetricsDTO $dto): Experiment
    {
        $experiment = $this->find($dto->experimentId, $dto->accountId);

        $this->metrics->upsertForDate($dto);

        return $this->repository->setSpendTotal(
            $experiment,
            $this->metrics->sumSpend($dto->experimentId, $dto->accountId),
        );
    }

    public function close(
        int $experimentId,
        int $accountId,
        ?int $userId = null,
        ActionOrigin $origin = ActionOrigin::UI,
    ): Experiment {
        $experiment = $this->find($experimentId, $accountId);

        $this->actionLog->record(new RecordActionDTO(
            $accountId,
            $userId,
            'experiment.closed',
            $origin,
            ['code' => $experiment->code, 'closed_early' => $this->isClosingEarly($experiment)],
            self::ENTITY_TYPE,
            $experimentId,
        ));

        return $this->repository->setStatus(
            $experiment,
            ExperimentStatus::Completed,
            $this->isClosingEarly($experiment),
        );
    }

    private function assertStrategyAcceptsExperiments(Strategy $strategy): void
    {
        if ($strategy->status === StrategyStatus::Archived) {
            throw StrategyArchivedException::withId((int) $strategy->id);
        }
    }

    private function withGeneratedWarnings(Experiment $experiment): Experiment
    {
        if ($experiment->type === ExperimentType::Ads) {
            $this->warningGenerator->generateFor($experiment);
        }

        return $this->find((int) $experiment->id, (int) $experiment->account_id);
    }

    private function assertExpectedResultIsExplicit(
        int $strategyId,
        ?array $expectedResult,
        ?CarbonImmutable $endsAt,
    ): void {
        if (ExpectedResult::isComplete($expectedResult) && $endsAt !== null) {
            return;
        }

        throw ExperimentWithoutExpectedResultException::forStrategy($strategyId, array_keys(array_filter([
            'expected_result' => ! ExpectedResult::isComplete($expectedResult),
            'ends_at' => $endsAt === null,
        ])));
    }

    private function assertDurationIsSufficient(
        ExperimentType $type,
        CarbonImmutable $startsAt,
        ?CarbonImmutable $endsAt,
    ): void {
        if ($type !== ExperimentType::Ads || $endsAt === null) {
            return;
        }

        if ((int) $startsAt->diffInDays($endsAt) < $this->rules->minimumDurationDays()) {
            throw ExperimentDurationTooShortException::needsDays(
                (int) $startsAt->diffInDays($endsAt),
                $this->rules->minimumDurationDays(),
            );
        }
    }

    private function assertBudgetWithinCaps(
        Strategy $strategy,
        ExperimentType $type,
        int $accountId,
        ?float $maxBudget,
        CarbonImmutable $startsAt,
        ?int $excludingExperimentId,
    ): void {
        if ($maxBudget === null) {
            $this->assertUncappedIsAllowed($strategy, $type);

            return;
        }

        if ($maxBudget > (float) $this->settings->get(self::MAX_BUDGET_SETTING, $accountId)) {
            throw ExperimentBudgetExceedsCapException::overAccountCap(
                $maxBudget,
                (float) $this->settings->get(self::MAX_BUDGET_SETTING, $accountId),
            );
        }

        if ($strategy->monthly_budget === null) {
            return;
        }

        if ($maxBudget > $this->remainingStrategyBudget($strategy, $accountId, $startsAt, $excludingExperimentId)) {
            throw ExperimentBudgetExceedsCapException::overStrategyBudget(
                $maxBudget,
                $this->remainingStrategyBudget($strategy, $accountId, $startsAt, $excludingExperimentId),
                (int) $strategy->id,
            );
        }
    }

    /** An uncapped experiment under a budgeted strategy is money nobody bounded; it cannot exist. */
    private function assertUncappedIsAllowed(Strategy $strategy, ExperimentType $type): void
    {
        if ($type === ExperimentType::Ads && $strategy->monthly_budget !== null) {
            throw ExperimentBudgetNotVerifiableException::uncappedUnderBudgetedStrategy((int) $strategy->id);
        }
    }

    private function remainingStrategyBudget(
        Strategy $strategy,
        int $accountId,
        CarbonImmutable $month,
        ?int $excludingExperimentId,
    ): float {
        $committing = $this->repository->budgetCommittingForMonth(
            (int) $strategy->id,
            $accountId,
            $month,
            $excludingExperimentId,
        );

        $uncapped = $committing->whereNull('max_budget');

        if ($uncapped->isNotEmpty()) {
            throw ExperimentBudgetNotVerifiableException::strategyHasUncappedExperiments(
                (int) $strategy->id,
                $uncapped->pluck('code')->all(),
            );
        }

        return (float) $strategy->monthly_budget - (float) $committing->sum('max_budget');
    }

    private function learningPhaseResetFor(Experiment $experiment, UpdateExperimentDTO $dto): ?CarbonImmutable
    {
        return $experiment->type === ExperimentType::Ads
            && $this->learningPhase->resetsLearning($this->changesFrom($experiment, $dto))
            ? $this->learningPhase->endsAt(CarbonImmutable::now())
            : null;
    }

    /**
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function changesFrom(Experiment $experiment, UpdateExperimentDTO $dto): array
    {
        return collect(LearningResettingChange::cases())
            ->mapWithKeys(fn (LearningResettingChange $field): array => [
                $field->value => $this->changedConfiguration($experiment, $dto, $field->value),
            ])
            ->put('max_budget', $this->changedBudget($experiment, $dto))
            ->filter()
            ->all();
    }

    /**
     * @return array{from: mixed, to: mixed}|null
     */
    private function changedConfiguration(Experiment $experiment, UpdateExperimentDTO $dto, string $key): ?array
    {
        return array_key_exists($key, $dto->configuration ?? [])
            && $dto->configuration[$key] !== ($experiment->configuration[$key] ?? null)
            ? ['from' => $experiment->configuration[$key] ?? null, 'to' => $dto->configuration[$key]]
            : null;
    }

    /**
     * @return array{from: mixed, to: mixed}|null
     */
    private function changedBudget(Experiment $experiment, UpdateExperimentDTO $dto): ?array
    {
        return $dto->maxBudget !== null && $dto->maxBudget !== (float) $experiment->max_budget
            ? ['from' => (float) $experiment->max_budget, 'to' => $dto->maxBudget]
            : null;
    }

    private function currentMaxBudget(Experiment $experiment): ?float
    {
        return $experiment->max_budget === null ? null : (float) $experiment->max_budget;
    }

    private function isClosingEarly(Experiment $experiment): bool
    {
        return CarbonImmutable::now()->lessThan($experiment->ends_at);
    }
}
