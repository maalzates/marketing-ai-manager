<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Application\Services;

use App\Modules\Experiments\Domain\Contracts\ExperimentWarningRepositoryInterface;
use App\Modules\Experiments\Domain\Enums\MetaAdsRule;
use App\Modules\Experiments\Domain\Enums\WarningCode;
use App\Modules\Experiments\Domain\Enums\WarningSeverity;
use App\Modules\Experiments\Domain\ValueObjects\ExpectedResult;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use App\Modules\Experiments\Infrastructure\Persistence\ExperimentWarning;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Turns the generic Meta rules into the warnings this one experiment will actually live
 * with: its own dates, its own amounts. A rule the user has to instantiate themselves is
 * a rule they will not read.
 */
readonly class ExperimentWarningGenerator
{
    private const string DATE_FORMAT = 'd/m/Y';

    public function __construct(
        private MetaAdsRuleService $rules,
        private LearningPhaseService $learningPhase,
        private ExperimentWarningRepositoryInterface $repository,
    ) {}

    /**
     * @return Collection<int, ExperimentWarning>
     */
    public function generateFor(Experiment $experiment): Collection
    {
        return $this->repository->createMany(
            (int) $experiment->id,
            (int) $experiment->account_id,
            $this->warningsFor($experiment),
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function warningsFor(Experiment $experiment): Collection
    {
        return collect([
            $this->learningWindowWarning($experiment),
            $this->minimumEvaluationDateWarning($experiment),
            $this->minimumDailyBudgetWarning($experiment),
            $this->defaultedRulesWarning(),
        ])->filter()->values();
    }

    /**
     * The figures below drive spending decisions. If any of them came from a documented
     * default instead of the live knowledge base, the user has to be told in the same
     * place they read the numbers — a stale threshold that looks current is worse than no
     * threshold at all.
     *
     * @return array<string, mixed>|null
     */
    private function defaultedRulesWarning(): ?array
    {
        if ($this->rules->unavailableRules()->isEmpty()) {
            return null;
        }

        return [
            'code' => WarningCode::DomainRuleUnavailable->value,
            'severity' => WarningSeverity::Warning,
            'applies_from' => null,
            'applies_to' => null,
            'message' => sprintf(
                'Estas advertencias se calcularon con los valores por defecto documentados: no se pudieron '
                .'leer de la base de conocimiento las reglas %s. Contrasta los importes y las fechas contra '
                .'la documentación vigente de Meta antes de comprometer gasto, y pide a un administrador que '
                .'revise esas entradas.',
                $this->rules->unavailableRules()
                    ->map(fn (MetaAdsRule $rule): string => $rule->value)
                    ->implode(', '),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function learningWindowWarning(Experiment $experiment): array
    {
        return [
            'code' => WarningCode::LearningPhaseWindow->value,
            'severity' => WarningSeverity::Info,
            'applies_from' => $this->learningPhase->windowFor($experiment)['starts_at'],
            'applies_to' => $this->learningPhase->windowFor($experiment)['ends_at'],
            'message' => sprintf(
                'Fase de aprendizaje: Meta necesita ~%d eventos de optimización en una ventana móvil de %d '
                .'días para estabilizar la entrega. No edites ni saques conclusiones entre el %s y el %s — '
                .'los costos más altos y volátiles de esos días son normales, no una campaña rota.',
                $this->rules->learningEventsNeeded(),
                $this->rules->learningWindowDays(),
                $this->learningPhase->windowFor($experiment)['starts_at']->format(self::DATE_FORMAT),
                $this->learningPhase->windowFor($experiment)['ends_at']->format(self::DATE_FORMAT),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function minimumEvaluationDateWarning(Experiment $experiment): array
    {
        return [
            'code' => WarningCode::MinimumEvaluationDate->value,
            'severity' => WarningSeverity::Info,
            'applies_from' => $this->evaluableFrom($experiment),
            'applies_to' => null,
            'message' => sprintf(
                'No evalúes este experimento antes del %s: son %d días desde el inicio (%s). Evaluar antes '
                .'es evaluar ruido.',
                $this->evaluableFrom($experiment)->format(self::DATE_FORMAT),
                $this->rules->minimumDurationDays(),
                $experiment->starts_at->format(self::DATE_FORMAT),
            ),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function minimumDailyBudgetWarning(Experiment $experiment): ?array
    {
        $expected = ExpectedResult::fromArray($experiment->expected_result);

        if (! $expected->isCostPerAction()) {
            return null;
        }

        return [
            'code' => WarningCode::MinimumDailyBudget->value,
            'severity' => $this->isUnderfunded($experiment, $expected) ? WarningSeverity::Critical : WarningSeverity::Warning,
            'applies_from' => $experiment->starts_at,
            'applies_to' => $experiment->ends_at,
            'message' => $this->minimumDailyBudgetMessage($experiment, $expected),
        ];
    }

    private function minimumDailyBudgetMessage(Experiment $experiment, ExpectedResult $expected): string
    {
        return $this->arithmeticSentence($expected).' '.match (true) {
            $this->configuredDailyBudget($experiment) === null => 'Este experimento no tiene presupuesto '
                .'configurado, así que no hay nada que comparar contra ese mínimo.',
            $this->isUnderfunded($experiment, $expected) => sprintf(
                'Tu configuración da %s al día, por debajo de ese mínimo: el ad set se queda sin salir de la '
                .'fase de aprendizaje. Dos alternativas: subir el presupuesto hasta al menos %s al día, u '
                .'optimizar por un evento del funnel más frecuente (por ejemplo Add to Cart en vez de Purchase).',
                number_format((float) $this->configuredDailyBudget($experiment), 2),
                number_format($this->rules->minimumDailyBudgetFor($expected->value), 2),
            ),
            default => sprintf(
                'Tu configuración da %s al día, que lo cubre.',
                number_format((float) $this->configuredDailyBudget($experiment), 2),
            ),
        };
    }

    private function arithmeticSentence(ExpectedResult $expected): string
    {
        return sprintf(
            'Con un %s objetivo de %s, el presupuesto diario mínimo matemático es %s — (%s × %d) ÷ %d.',
            strtoupper($expected->metric),
            number_format($expected->value, 2),
            number_format($this->rules->minimumDailyBudgetFor($expected->value), 2),
            number_format($expected->value, 2),
            $this->rules->budgetFormulaEvents(),
            $this->rules->budgetFormulaWindowDays(),
        );
    }

    private function isUnderfunded(Experiment $experiment, ExpectedResult $expected): bool
    {
        return $this->configuredDailyBudget($experiment) !== null
            && $this->configuredDailyBudget($experiment) < $this->rules->minimumDailyBudgetFor($expected->value);
    }

    private function configuredDailyBudget(Experiment $experiment): ?float
    {
        return $experiment->max_budget === null
            ? null
            : (float) $experiment->max_budget / max(1, $this->durationDays($experiment));
    }

    private function durationDays(Experiment $experiment): int
    {
        return (int) $experiment->starts_at->diffInDays($experiment->ends_at);
    }

    private function evaluableFrom(Experiment $experiment): CarbonImmutable
    {
        return $experiment->starts_at->addDays($this->rules->minimumDurationDays());
    }
}
