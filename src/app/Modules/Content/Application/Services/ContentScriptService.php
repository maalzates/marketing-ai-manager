<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Services;

use App\Modules\Content\Application\DTO\ContentScriptFilterDTO;
use App\Modules\Content\Application\DTO\CreateContentScriptDTO;
use App\Modules\Content\Application\DTO\UpdateContentScriptDTO;
use App\Modules\Content\Domain\Contracts\ContentScriptRepositoryInterface;
use App\Modules\Content\Domain\Enums\ScriptStatus;
use App\Modules\Content\Domain\Exceptions\ContentScriptNotFoundException;
use App\Modules\Content\Domain\Exceptions\ScriptAlreadyApprovedException;
use App\Modules\Content\Domain\Exceptions\ScriptApprovalRequiresExperimentException;
use App\Modules\Content\Infrastructure\Persistence\ContentScript;
use App\Modules\Strategies\Application\Services\StrategyService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

readonly class ContentScriptService
{
    public function __construct(
        private ContentScriptRepositoryInterface $repository,
        private StrategyService $strategies,
    ) {}

    /**
     * @return Collection<int, ContentScript>|LengthAwarePaginator<int, ContentScript>
     */
    public function forAccount(ContentScriptFilterDTO $filters): Collection|LengthAwarePaginator
    {
        return $this->repository->findAll($filters);
    }

    public function find(int $id, int $accountId): ContentScript
    {
        return $this->repository->findById($id, $accountId) ?? throw ContentScriptNotFoundException::withId($id);
    }

    public function create(CreateContentScriptDTO $dto): ContentScript
    {
        $this->strategies->find($dto->strategyId, $dto->accountId);

        return $this->repository->create($dto);
    }

    /** An approved script is the written form of a live experiment, so it is frozen. */
    public function update(UpdateContentScriptDTO $dto): ContentScript
    {
        $script = $this->find($dto->scriptId, $dto->accountId);

        if ($dto->status === ScriptStatus::Approved) {
            throw ScriptApprovalRequiresExperimentException::withId($script->id);
        }

        return $script->status === ScriptStatus::Approved
            ? throw ScriptAlreadyApprovedException::withId($script->id, $script->experiment_id)
            : $this->repository->update($script, $dto);
    }
}
