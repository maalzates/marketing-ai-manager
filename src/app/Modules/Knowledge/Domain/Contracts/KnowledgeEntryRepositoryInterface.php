<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Domain\Contracts;

use App\Modules\Knowledge\Application\DTO\CreateKnowledgeEntryDTO;
use App\Modules\Knowledge\Application\DTO\KnowledgeFilterDTO;
use App\Modules\Knowledge\Domain\Enums\KnowledgeType;
use App\Modules\Knowledge\Infrastructure\Persistence\KnowledgeEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface KnowledgeEntryRepositoryInterface
{
    public function findAll(KnowledgeFilterDTO $filters): Collection|LengthAwarePaginator;

    public function findById(int $id): ?KnowledgeEntry;

    public function latestPublished(KnowledgeType $type, string $key, string $locale): ?KnowledgeEntry;

    /** @return Collection<int, KnowledgeEntry> */
    public function latestPublishedByType(KnowledgeType $type, string $locale): Collection;

    public function create(CreateKnowledgeEntryDTO $dto): KnowledgeEntry;

    public function setPublished(KnowledgeEntry $entry, bool $isPublished): KnowledgeEntry;

    public function delete(KnowledgeEntry $entry): bool;
}
