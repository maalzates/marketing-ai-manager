<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Repositories;

use App\Modules\Content\Application\DTO\ContentScriptFilterDTO;
use App\Modules\Content\Application\DTO\CreateContentScriptDTO;
use App\Modules\Content\Application\DTO\UpdateContentScriptDTO;
use App\Modules\Content\Domain\Contracts\ContentScriptRepositoryInterface;
use App\Modules\Content\Domain\Enums\ContentFormat;
use App\Modules\Content\Domain\Enums\ScriptStatus;
use App\Modules\Content\Domain\Exceptions\ContentPersistenceFailedException;
use App\Modules\Content\Infrastructure\Persistence\ContentScript;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

readonly class ContentScriptRepository implements ContentScriptRepositoryInterface
{
    public function __construct(private ContentScript $model) {}

    public function findAll(ContentScriptFilterDTO $filters): Collection|LengthAwarePaginator
    {
        return $filters->perPage > 0
            ? $this->query($filters)->paginate(perPage: $filters->perPage, page: $filters->page)
            : $this->query($filters)->get();
    }

    public function findById(int $id, int $accountId): ?ContentScript
    {
        return $this->model->newQuery()->where('account_id', $accountId)->find($id);
    }

    public function findByExperiment(int $experimentId, int $accountId): ?ContentScript
    {
        return $this->model->newQuery()
            ->where('account_id', $accountId)
            ->where('experiment_id', $experimentId)
            ->first();
    }

    public function recentTitles(int $accountId, int $strategyId, int $limit): Collection
    {
        return $this->model->newQuery()
            ->where('account_id', $accountId)
            ->where('strategy_id', $strategyId)
            ->latest('id')
            ->limit($limit)
            ->get(['id', 'title', 'hook', 'format', 'status']);
    }

    public function create(CreateContentScriptDTO $dto): ContentScript
    {
        try {
            return $this->model->newQuery()->create([
                'account_id' => $dto->accountId,
                'strategy_id' => $dto->strategyId,
                'title' => $dto->title,
                'hook' => $dto->hook,
                'structure' => $dto->structure,
                'cta' => $dto->cta,
                'format' => $dto->format,
                'required_assets' => $dto->requiredAssets,
                'source_insight_ids' => $dto->sourceInsightIds,
            ]);
        } catch (Throwable $exception) {
            throw ContentPersistenceFailedException::wrap($exception, context: [
                'account_id' => $dto->accountId,
                'strategy_id' => $dto->strategyId,
                'title' => $dto->title,
            ]);
        }
    }

    public function update(ContentScript $script, UpdateContentScriptDTO $dto): ContentScript
    {
        try {
            $script->update(array_filter([
                'title' => $dto->title,
                'hook' => $dto->hook,
                'structure' => $dto->structure,
                'cta' => $dto->cta,
                'format' => $dto->format,
                'required_assets' => $dto->requiredAssets,
                'status' => $dto->status,
            ], fn (mixed $value): bool => $value !== null));

            return $script->refresh();
        } catch (Throwable $exception) {
            throw ContentPersistenceFailedException::wrap($exception, context: ['content_script_id' => $script->id]);
        }
    }

    public function approve(ContentScript $script, int $experimentId): ContentScript
    {
        try {
            $script->update(['status' => ScriptStatus::Approved, 'experiment_id' => $experimentId]);

            return $script->refresh();
        } catch (Throwable $exception) {
            throw ContentPersistenceFailedException::wrap($exception, context: [
                'content_script_id' => $script->id,
                'experiment_id' => $experimentId,
            ]);
        }
    }

    private function query(ContentScriptFilterDTO $filters): Builder
    {
        return $this->model->newQuery()
            ->where('account_id', $filters->accountId)
            ->when($filters->strategyId, fn (Builder $query, int $strategyId) => $query->where('strategy_id', $strategyId))
            ->when($filters->status, fn (Builder $query, ScriptStatus $status) => $query->where('status', $status))
            ->when($filters->format, fn (Builder $query, ContentFormat $format) => $query->where('format', $format))
            ->latest('id');
    }
}
