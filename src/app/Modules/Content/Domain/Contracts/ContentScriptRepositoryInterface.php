<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Contracts;

use App\Modules\Content\Application\DTO\ContentScriptFilterDTO;
use App\Modules\Content\Application\DTO\CreateContentScriptDTO;
use App\Modules\Content\Application\DTO\UpdateContentScriptDTO;
use App\Modules\Content\Infrastructure\Persistence\ContentScript;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ContentScriptRepositoryInterface
{
    /** @return Collection<int, ContentScript>|LengthAwarePaginator<int, ContentScript> */
    public function findAll(ContentScriptFilterDTO $filters): Collection|LengthAwarePaginator;

    public function findById(int $id, int $accountId): ?ContentScript;

    public function findByExperiment(int $experimentId, int $accountId): ?ContentScript;

    /** @return Collection<int, ContentScript> */
    public function recentTitles(int $accountId, int $strategyId, int $limit): Collection;

    public function create(CreateContentScriptDTO $dto): ContentScript;

    public function update(ContentScript $script, UpdateContentScriptDTO $dto): ContentScript;

    public function approve(ContentScript $script, int $experimentId): ContentScript;
}
