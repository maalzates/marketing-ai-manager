<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Infrastructure\Repositories;

use App\Modules\Knowledge\Application\DTO\CreateKnowledgeEntryDTO;
use App\Modules\Knowledge\Application\DTO\KnowledgeFilterDTO;
use App\Modules\Knowledge\Domain\Contracts\KnowledgeEntryRepositoryInterface;
use App\Modules\Knowledge\Domain\Enums\KnowledgeType;
use App\Modules\Knowledge\Domain\Exceptions\KnowledgeEntryCreationFailedException;
use App\Modules\Knowledge\Domain\Exceptions\KnowledgeEntryUpdateFailedException;
use App\Modules\Knowledge\Infrastructure\Persistence\KnowledgeEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

readonly class KnowledgeEntryRepository implements KnowledgeEntryRepositoryInterface
{
    public function __construct(private KnowledgeEntry $model) {}

    public function findAll(KnowledgeFilterDTO $filters): Collection|LengthAwarePaginator
    {
        return $filters->perPage > 0
            ? $this->query($filters)->paginate(perPage: $filters->perPage, page: $filters->page)
            : $this->query($filters)->get();
    }

    public function findById(int $id): ?KnowledgeEntry
    {
        return $this->model->newQuery()->find($id);
    }

    public function latestPublished(KnowledgeType $type, string $key, string $locale): ?KnowledgeEntry
    {
        return $this->publishedQuery($type, $locale)
            ->where('key', $key)
            ->orderByDesc('version')
            ->first();
    }

    public function latestPublishedByType(KnowledgeType $type, string $locale): Collection
    {
        return $this->publishedQuery($type, $locale)
            ->orderBy('key')
            ->orderBy('version')
            ->get()
            ->keyBy('key')
            ->values();
    }

    public function create(CreateKnowledgeEntryDTO $dto): KnowledgeEntry
    {
        try {
            return $this->model->newQuery()->create([
                'type' => $dto->type,
                'key' => $dto->key,
                'locale' => $dto->locale,
                'title' => $dto->title,
                'body' => $dto->body,
                'metadata' => $dto->metadata,
                'version' => $this->nextVersion($dto->type, $dto->key, $dto->locale),
                'is_published' => $dto->isPublished,
            ]);
        } catch (Throwable $exception) {
            throw KnowledgeEntryCreationFailedException::wrap($exception, context: [
                'type' => $dto->type->value,
                'key' => $dto->key,
                'locale' => $dto->locale,
            ]);
        }
    }

    public function setPublished(KnowledgeEntry $entry, bool $isPublished): KnowledgeEntry
    {
        try {
            $entry->update(['is_published' => $isPublished]);

            return $entry->refresh();
        } catch (Throwable $exception) {
            throw KnowledgeEntryUpdateFailedException::wrap($exception, context: [
                'knowledge_entry_id' => $entry->id,
                'is_published' => $isPublished,
            ]);
        }
    }

    public function delete(KnowledgeEntry $entry): bool
    {
        return (bool) $entry->delete();
    }

    private function query(KnowledgeFilterDTO $filters): Builder
    {
        return $this->model->newQuery()
            ->when($filters->type, fn (Builder $query, KnowledgeType $type) => $query->where('type', $type))
            ->when($filters->key, fn (Builder $query, string $key) => $query->where('key', $key))
            ->when($filters->locale, fn (Builder $query, string $locale) => $query->where('locale', $locale))
            ->when(
                $filters->isPublished !== null,
                fn (Builder $query) => $query->where('is_published', $filters->isPublished),
            )
            ->orderBy('type')
            ->orderBy('key')
            ->orderByDesc('version');
    }

    private function publishedQuery(KnowledgeType $type, string $locale): Builder
    {
        return $this->model->newQuery()
            ->where('type', $type)
            ->where('locale', $locale)
            ->where('is_published', true);
    }

    private function nextVersion(KnowledgeType $type, string $key, string $locale): int
    {
        return (int) $this->model->newQuery()
            ->where('type', $type)
            ->where('key', $key)
            ->where('locale', $locale)
            ->max('version') + 1;
    }
}
