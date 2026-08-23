<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Application\Services;

use App\Modules\Knowledge\Application\DTO\CreateKnowledgeEntryDTO;
use App\Modules\Knowledge\Application\DTO\KnowledgeFilterDTO;
use App\Modules\Knowledge\Application\DTO\UpdateKnowledgeEntryDTO;
use App\Modules\Knowledge\Domain\Contracts\KnowledgeEntryRepositoryInterface;
use App\Modules\Knowledge\Domain\Enums\KnowledgeType;
use App\Modules\Knowledge\Domain\Exceptions\KnowledgeEntryNotFoundException;
use App\Modules\Knowledge\Infrastructure\Persistence\KnowledgeEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

readonly class KnowledgeService
{
    public const string DEFAULT_LOCALE = 'es';

    public function __construct(private KnowledgeEntryRepositoryInterface $repository) {}

    public function latest(
        KnowledgeType $type,
        string $key,
        string $locale = self::DEFAULT_LOCALE,
    ): KnowledgeEntry {
        return $this->repository->latestPublished($type, $key, $locale)
            ?? throw KnowledgeEntryNotFoundException::withKey($type, $key, $locale);
    }

    /** @return Collection<int, KnowledgeEntry> */
    public function listByType(KnowledgeType $type, string $locale = self::DEFAULT_LOCALE): Collection
    {
        return $this->repository->latestPublishedByType($type, $locale);
    }

    /**
     * The static head of every LLM system prompt. Order and formatting must stay stable
     * across calls or the provider cannot cache the prefix, which is the whole point.
     */
    public function systemPrompt(string $locale = self::DEFAULT_LOCALE): string
    {
        return $this->listByType(KnowledgeType::DomainRule, $locale)
            ->map(fn (KnowledgeEntry $entry): string => "## {$entry->title}\n\n{$entry->body}")
            ->implode("\n\n");
    }

    public function findAll(KnowledgeFilterDTO $filters): Collection|LengthAwarePaginator
    {
        return $this->repository->findAll($filters);
    }

    public function findById(int $id): KnowledgeEntry
    {
        return $this->repository->findById($id) ?? throw KnowledgeEntryNotFoundException::withId($id);
    }

    public function create(CreateKnowledgeEntryDTO $dto): KnowledgeEntry
    {
        return $this->repository->create($dto);
    }

    /**
     * An update never mutates a row: it inserts the next version of the same
     * type/key/locale, so the admin can roll back by unpublishing it.
     */
    public function update(UpdateKnowledgeEntryDTO $dto): KnowledgeEntry
    {
        $current = $this->findById($dto->id);

        return $this->repository->create(new CreateKnowledgeEntryDTO(
            $current->type,
            $current->key,
            $current->locale,
            $dto->title ?? $current->title,
            $dto->body ?? $current->body,
            $dto->metadata ?? $current->metadata,
            $dto->isPublished ?? $current->is_published,
        ));
    }

    public function publish(int $id): KnowledgeEntry
    {
        return $this->repository->setPublished($this->findById($id), true);
    }

    public function unpublish(int $id): KnowledgeEntry
    {
        return $this->repository->setPublished($this->findById($id), false);
    }

    public function delete(int $id): bool
    {
        return $this->repository->delete($this->findById($id));
    }
}
