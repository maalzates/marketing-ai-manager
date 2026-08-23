<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Domain\Contracts;

use App\Modules\Experiments\Application\DTO\CreateExperimentDTO;
use App\Modules\Experiments\Application\DTO\ExperimentFilterDTO;
use App\Modules\Experiments\Application\DTO\UpdateExperimentDTO;
use App\Modules\Experiments\Domain\Enums\ExperimentStatus;
use App\Modules\Experiments\Domain\Enums\Verdict;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ExperimentRepositoryInterface
{
    /**
     * @return Collection<int, Experiment>|LengthAwarePaginator<int, Experiment>
     */
    public function findAll(ExperimentFilterDTO $filters): Collection|LengthAwarePaginator;

    public function findById(int $id, int $accountId): ?Experiment;

    public function nextCode(int $accountId): string;

    /** Any experiment at all, whatever its status: a completed verdict is history worth protecting. */
    public function existsForStrategy(int $strategyId, int $accountId): bool;

    /**
     * The experiments whose `max_budget` is still promised to the strategy in $month. The
     * caller sums them, so an uncapped one is visible instead of silently counting as zero.
     *
     * @return Collection<int, Experiment>
     */
    public function budgetCommittingForMonth(
        int $strategyId,
        int $accountId,
        CarbonImmutable $month,
        ?int $excludingExperimentId = null,
    ): Collection;

    public function create(
        CreateExperimentDTO $dto,
        string $code,
        ?CarbonImmutable $learningPhaseEndsAt,
    ): Experiment;

    public function update(
        Experiment $experiment,
        UpdateExperimentDTO $dto,
        ?CarbonImmutable $learningPhaseEndsAt,
    ): Experiment;

    public function setSpendTotal(Experiment $experiment, float $spendTotal): Experiment;

    public function confirmVerdict(
        Experiment $experiment,
        Verdict $verdict,
        string $reason,
        bool $closedEarly,
    ): Experiment;

    public function setStatus(Experiment $experiment, ExperimentStatus $status, bool $closedEarly): Experiment;
}
