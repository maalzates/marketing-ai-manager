<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Domain\Contracts;

use App\Modules\Competitors\Application\DTO\CompetitorCommentDTO;
use App\Modules\Competitors\Infrastructure\Persistence\CompetitorComment;
use Illuminate\Support\Collection;

interface CompetitorCommentRepositoryInterface
{
    /**
     * @param  Collection<int, CompetitorCommentDTO>  $comments
     * @return Collection<int, CompetitorComment>
     */
    public function upsertMany(int $accountId, int $postId, Collection $comments): Collection;

    /**
     * @return Collection<int, CompetitorComment>
     */
    public function forPost(int $accountId, int $postId, int $limit): Collection;

    /**
     * Everything long enough and recent enough to be worth clustering — spam, one-word
     * replies and link drops are excluded here, in SQL, before anything is loaded.
     *
     * @return Collection<int, CompetitorComment>
     */
    public function miningCandidates(int $accountId, int $competitorId, int $minimumLength, int $limit): Collection;
}
