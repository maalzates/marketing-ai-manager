<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Domain\Contracts;

use App\Modules\Competitors\Application\DTO\CompetitorPostDTO;
use App\Modules\Competitors\Application\DTO\CompetitorPostFilterDTO;
use App\Modules\Competitors\Domain\Enums\Sentiment;
use App\Modules\Competitors\Infrastructure\Persistence\CompetitorPost;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CompetitorPostRepositoryInterface
{
    /**
     * @return Collection<int, CompetitorPost>|LengthAwarePaginator<int, CompetitorPost>
     */
    public function findAll(CompetitorPostFilterDTO $filters): Collection|LengthAwarePaginator;

    /**
     * @param  Collection<int, CompetitorPostDTO>  $posts
     * @return Collection<int, CompetitorPost>
     */
    public function upsertMany(int $accountId, int $competitorId, Collection $posts): Collection;

    /**
     * @return Collection<int, CompetitorPost>
     */
    public function topByEngagement(int $accountId, int $competitorId, int $limit): Collection;

    /**
     * @return Collection<int, CompetitorPost>
     */
    public function withComments(int $accountId, int $competitorId, int $limit): Collection;

    /**
     * @return Collection<int, CompetitorPost>
     */
    public function awaitingSentiment(int $accountId, int $competitorId, int $limit): Collection;

    public function storeSentiment(CompetitorPost $post, Sentiment $sentiment, array $summary): CompetitorPost;

    /**
     * Averages per post type, computed by the database so no batch of posts has to be
     * loaded into memory just to be summed.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function engagementByType(int $accountId, int $competitorId): Collection;
}
