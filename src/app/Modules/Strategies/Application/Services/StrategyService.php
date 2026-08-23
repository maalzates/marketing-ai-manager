<?php

declare(strict_types=1);

namespace App\Modules\Strategies\Application\Services;

use App\Modules\Audit\Application\DTO\RecordActionDTO;
use App\Modules\Audit\Application\Services\ActionLogService;
use App\Modules\Brands\Application\Services\BrandProfileService;
use App\Modules\Settings\Application\Services\SettingsService;
use App\Modules\Strategies\Application\DTO\ArchiveStrategyDTO;
use App\Modules\Strategies\Application\DTO\CreateStrategyDTO;
use App\Modules\Strategies\Application\DTO\StrategyFilterDTO;
use App\Modules\Strategies\Application\DTO\UpdateStrategyDTO;
use App\Modules\Strategies\Domain\Contracts\StrategyRepositoryInterface;
use App\Modules\Strategies\Domain\Contracts\StrategyWorkloadProviderInterface;
use App\Modules\Strategies\Domain\Enums\StrategyStatus;
use App\Modules\Strategies\Domain\Exceptions\StrategyArchivedException;
use App\Modules\Strategies\Domain\Exceptions\StrategyBudgetExceedsAccountCapException;
use App\Modules\Strategies\Domain\Exceptions\StrategyInUseException;
use App\Modules\Strategies\Domain\Exceptions\StrategyNotFoundException;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

readonly class StrategyService
{
    private const string ARCHIVE_ACTION = 'strategy.archived';

    private const string BUDGET_CAP_SETTING = 'budgets.max_monthly_per_strategy';

    private const string ENTITY_TYPE = 'strategy';

    public function __construct(
        private StrategyRepositoryInterface $repository,
        private BrandProfileService $brandProfiles,
        private SettingsService $settings,
        private ActionLogService $actionLog,
        private StrategyWorkloadProviderInterface $workload,
    ) {}

    /**
     * @return Collection<int, Strategy>|LengthAwarePaginator<int, Strategy>
     */
    public function forAccount(StrategyFilterDTO $filters): Collection|LengthAwarePaginator
    {
        return $this->repository->findAll($filters);
    }

    public function find(int $id, int $accountId): Strategy
    {
        return $this->repository->findById($id, $accountId) ?? throw StrategyNotFoundException::withId($id);
    }

    public function create(CreateStrategyDTO $dto): Strategy
    {
        $this->assertBrandProfileIsOwned($dto->brandProfileId, $dto->accountId);
        $this->assertBudgetWithinCap($dto->monthlyBudget, $dto->accountId);

        return $this->repository->create($dto);
    }

    public function update(UpdateStrategyDTO $dto): Strategy
    {
        $this->assertBudgetWithinCap($dto->monthlyBudget, $dto->accountId);

        return $this->repository->update(
            $this->findActionable($dto->strategyId, $dto->accountId),
            $dto,
        );
    }

    /**
     * A strategy that already carries work is archived, never deleted: its experiments hold
     * the verdicts that are the whole memory of the system.
     */
    public function delete(int $id, int $accountId): bool
    {
        return $this->workload->hasRecordedWork($id, $accountId)
            ? throw StrategyInUseException::withId($id)
            : $this->repository->delete($this->find($id, $accountId));
    }

    /** The only way out of `archived`, and deliberately a step of its own. */
    public function activate(int $id, int $accountId): Strategy
    {
        return $this->repository->changeStatus($this->find($id, $accountId), StrategyStatus::Active);
    }

    public function pause(int $id, int $accountId): Strategy
    {
        return $this->repository->changeStatus(
            $this->findActionable($id, $accountId),
            StrategyStatus::Paused,
        );
    }

    public function archive(ArchiveStrategyDTO $dto): Strategy
    {
        $strategy = $this->repository->changeStatus(
            $this->findActionable($dto->strategyId, $dto->accountId),
            StrategyStatus::Archived,
        );

        $this->actionLog->record(new RecordActionDTO(
            $dto->accountId,
            $dto->userId,
            self::ARCHIVE_ACTION,
            $dto->origin,
            ['name' => $strategy->name, 'north_star_metric' => $strategy->north_star_metric],
            self::ENTITY_TYPE,
            (int) $strategy->id,
        ));

        return $strategy;
    }

    /**
     * The compact strategy block for LLM context.
     *
     * @return array<string, mixed>
     */
    public function summary(int $id, int $accountId): array
    {
        $strategy = $this->find($id, $accountId);

        return [
            'objective' => $strategy->objective,
            'north_star_metric' => $strategy->north_star_metric,
            'monthly_budget' => $strategy->monthly_budget,
            'constraints' => $strategy->constraints,
            'status' => $strategy->status->value,
        ];
    }

    /** Archived is terminal: nothing changes on a strategy until it is activated again. */
    private function findActionable(int $id, int $accountId): Strategy
    {
        $strategy = $this->find($id, $accountId);

        return $strategy->status === StrategyStatus::Archived
            ? throw StrategyArchivedException::withId($id)
            : $strategy;
    }

    /**
     * Lives here rather than in a FormRequest because the cap has to hold for the chat
     * tools and the jobs too, not only for the HTTP door.
     */
    private function assertBudgetWithinCap(?float $budget, int $accountId): void
    {
        if ($budget === null) {
            return;
        }

        $cap = (float) $this->settings->get(self::BUDGET_CAP_SETTING, $accountId);

        if ($budget > $cap) {
            throw StrategyBudgetExceedsAccountCapException::forBudget($budget, $cap);
        }
    }

    private function assertBrandProfileIsOwned(int $brandProfileId, int $accountId): void
    {
        $this->brandProfiles->find($brandProfileId, $accountId);
    }
}
