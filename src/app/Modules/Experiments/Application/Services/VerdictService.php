<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Application\Services;

use App\Modules\Audit\Application\DTO\RecordActionDTO;
use App\Modules\Audit\Application\Services\ActionLogService;
use App\Modules\Audit\Domain\Enums\ActionOrigin;
use App\Modules\Experiments\Application\DTO\SuggestedVerdictDTO;
use App\Modules\Experiments\Domain\Contracts\ExperimentMetricRepositoryInterface;
use App\Modules\Experiments\Domain\Contracts\ExperimentRepositoryInterface;
use App\Modules\Experiments\Domain\Enums\ExperimentType;
use App\Modules\Experiments\Domain\Enums\Verdict;
use App\Modules\Experiments\Domain\ValueObjects\ExpectedResult;
use App\Modules\Experiments\Domain\ValueObjects\MetricTotals;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use Carbon\CarbonImmutable;

/**
 * The suggestion is arithmetic over the stored daily series, not prose from a model: a
 * verdict the code cannot justify from data is not a verdict.
 */
readonly class VerdictService
{
    private const string ENTITY_TYPE = 'experiment';

    public function __construct(
        private ExperimentService $experiments,
        private ExperimentRepositoryInterface $repository,
        private ExperimentMetricRepositoryInterface $metrics,
        private LearningPhaseService $learningPhase,
        private MetaAdsRuleService $rules,
        private ActionLogService $actionLog,
    ) {}

    public function suggest(int $experimentId, int $accountId): SuggestedVerdictDTO
    {
        $experiment = $this->experiments->find($experimentId, $accountId);

        return $this->judge(
            $experiment,
            ExpectedResult::fromArray($experiment->expected_result),
            MetricTotals::fromDaily($this->metrics->findForExperiment($experimentId, $accountId)),
        );
    }

    public function confirm(
        int $experimentId,
        int $accountId,
        Verdict $verdict,
        string $reason,
        ?int $userId = null,
        ActionOrigin $origin = ActionOrigin::UI,
    ): Experiment {
        $experiment = $this->experiments->find($experimentId, $accountId);

        $this->actionLog->record(new RecordActionDTO(
            $accountId,
            $userId,
            'experiment.verdict_confirmed',
            $origin,
            ['code' => $experiment->code, 'verdict' => $verdict->value, 'reason' => $reason],
            self::ENTITY_TYPE,
            $experimentId,
        ));

        return $this->repository->confirmVerdict(
            $experiment,
            $verdict,
            $reason,
            CarbonImmutable::now()->lessThan($experiment->ends_at),
        );
    }

    private function judge(
        Experiment $experiment,
        ExpectedResult $expected,
        MetricTotals $totals,
    ): SuggestedVerdictDTO {
        return match (true) {
            $totals->days === 0 => $this->inconclusive($experiment, $expected, null, $totals, sprintf(
                'No hay métricas registradas, así que no hay nada contra qué comparar el resultado esperado (%s %s %s).',
                $expected->metric,
                $expected->operator->label(),
                $this->format($expected->value),
            )),
            $totals->valueOf($expected->metric) === null => $this->inconclusive($experiment, $expected, null, $totals, sprintf(
                'La métrica "%s" no se puede calcular con los datos disponibles: o su denominador es cero o no '
                .'forma parte del glosario de métricas de la aplicación.',
                $expected->metric,
            )),
            $this->isTooEarlyToJudge($experiment, $totals) => $this->inconclusive(
                $experiment,
                $expected,
                $totals->valueOf($expected->metric),
                $totals,
                sprintf(
                    'Solo hay %d días de datos y un experimento de ads necesita %d, o el experimento sigue '
                    .'dentro de su ventana de aprendizaje (hasta el %s). Concluir ahora sería concluir sobre ruido.',
                    $totals->days,
                    $this->rules->minimumDurationDays(),
                    $this->learningPhase->windowFor($experiment)['ends_at']->format('d/m/Y'),
                ),
            ),
            default => $this->decided($experiment, $expected, $totals),
        };
    }

    private function decided(
        Experiment $experiment,
        ExpectedResult $expected,
        MetricTotals $totals,
    ): SuggestedVerdictDTO {
        return new SuggestedVerdictDTO(
            (int) $experiment->id,
            $expected->isSatisfiedBy((float) $totals->valueOf($expected->metric)) ? Verdict::Worked : Verdict::DidNotWork,
            sprintf(
                'El resultado esperado era %s %s %s y sobre %d días de datos el obtenido es %s: %s.',
                $expected->metric,
                $expected->operator->label(),
                $this->format($expected->value),
                $totals->days,
                $this->format((float) $totals->valueOf($expected->metric)),
                $expected->isSatisfiedBy((float) $totals->valueOf($expected->metric)) ? 'se cumplió' : 'no se cumplió',
            ),
            $expected,
            $totals->valueOf($expected->metric),
            $totals->days,
        );
    }

    private function inconclusive(
        Experiment $experiment,
        ExpectedResult $expected,
        ?float $actual,
        MetricTotals $totals,
        string $reasoning,
    ): SuggestedVerdictDTO {
        return new SuggestedVerdictDTO(
            (int) $experiment->id,
            Verdict::Inconclusive,
            $reasoning,
            $expected,
            $actual,
            $totals->days,
        );
    }

    private function isTooEarlyToJudge(Experiment $experiment, MetricTotals $totals): bool
    {
        return $experiment->type === ExperimentType::Ads
            && ($totals->days < $this->rules->minimumDurationDays()
                || $this->learningPhase->isWithinLearningWindow($experiment, CarbonImmutable::now()));
    }

    private function format(float $value): string
    {
        return number_format($value, 2);
    }
}
