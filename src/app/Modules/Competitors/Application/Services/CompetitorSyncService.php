<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Application\Services;

use App\Modules\Audit\Application\DTO\RecordApifyUsageDTO;
use App\Modules\Audit\Application\Services\UsageService;
use App\Modules\Competitors\Application\DTO\CompetitorCommentDTO;
use App\Modules\Competitors\Application\DTO\FetchAdsDTO;
use App\Modules\Competitors\Application\DTO\FetchCommentsDTO;
use App\Modules\Competitors\Application\DTO\FetchPostsDTO;
use App\Modules\Competitors\Application\DTO\ProviderRunResultDTO;
use App\Modules\Competitors\Domain\Contracts\CompetitorCommentRepositoryInterface;
use App\Modules\Competitors\Domain\Contracts\CompetitorDataProviderInterface;
use App\Modules\Competitors\Domain\Contracts\CompetitorPostRepositoryInterface;
use App\Modules\Competitors\Domain\Contracts\CompetitorRepositoryInterface;
use App\Modules\Competitors\Domain\Enums\CompetitorPlatform;
use App\Modules\Competitors\Domain\Exceptions\CompetitorNotFoundException;
use App\Modules\Competitors\Infrastructure\Persistence\Competitor;
use App\Modules\Competitors\Infrastructure\Persistence\CompetitorComment;
use App\Modules\Competitors\Infrastructure\Persistence\CompetitorPost;
use Illuminate\Support\Collection;

/**
 * Turns one provider run into rows. Nothing here knows the provider is Apify; the cost it
 * logs comes back with the result because only the provider saw the run that produced it.
 */
readonly class CompetitorSyncService
{
    private const int POSTS_PER_SYNC = 50;

    /** Comments are the expensive actor, so only the freshest posts are re-read. */
    private const int POSTS_TO_READ_COMMENTS_FROM = 10;

    private const int COMMENTS_PER_POST = 30;

    public function __construct(
        private CompetitorRepositoryInterface $competitors,
        private CompetitorPostRepositoryInterface $posts,
        private CompetitorCommentRepositoryInterface $comments,
        private CompetitorDataProviderInterface $provider,
        private UsageService $usage,
    ) {}

    /**
     * @return Collection<int, CompetitorPost>
     */
    public function syncPosts(int $accountId, int $competitorId): Collection
    {
        $competitor = $this->competitor($accountId, $competitorId);
        $result = $this->fetch($accountId, $competitor);
        $stored = $this->posts->upsertMany($accountId, $competitorId, $result->items);

        $this->record($accountId, $result, $stored->count());
        $this->competitors->markSynced($competitor);

        return $stored;
    }

    /**
     * @return Collection<int, CompetitorComment>
     */
    public function syncComments(int $accountId, int $competitorId): Collection
    {
        $posts = $this->posts->withComments($accountId, $competitorId, self::POSTS_TO_READ_COMMENTS_FROM);

        if ($posts->isEmpty()) {
            return collect();
        }

        $result = $this->provider->fetchComments(new FetchCommentsDTO(
            $accountId,
            $posts->pluck('url')->all(),
            self::COMMENTS_PER_POST,
        ));

        $this->record($accountId, $result, $result->items->count());

        return $this->store($accountId, $posts, $result->items);
    }

    private function fetch(int $accountId, Competitor $competitor): ProviderRunResultDTO
    {
        return $competitor->platform === CompetitorPlatform::FacebookAds
            ? $this->provider->fetchAds(new FetchAdsDTO($accountId, $competitor->handle, self::POSTS_PER_SYNC))
            : $this->provider->fetchPosts(new FetchPostsDTO(
                $accountId,
                $competitor->platform,
                $competitor->handle,
                self::POSTS_PER_SYNC,
            ));
    }

    /**
     * @param  Collection<int, CompetitorPost>  $posts
     * @param  Collection<int, CompetitorCommentDTO>  $comments
     * @return Collection<int, CompetitorComment>
     */
    private function store(int $accountId, Collection $posts, Collection $comments): Collection
    {
        // The actor echoes the URL it was given; a trailing slash difference would orphan
        // every comment it returned.
        $byUrl = $posts->keyBy(static fn (CompetitorPost $post): string => rtrim($post->url, '/'));

        return $comments
            ->groupBy(static fn (CompetitorCommentDTO $comment): string => rtrim($comment->postUrl, '/'))
            ->filter(static fn (Collection $group, string $url): bool => $byUrl->has($url))
            ->flatMap(fn (Collection $group, string $url): Collection => $this->comments->upsertMany(
                $accountId,
                (int) $byUrl->get($url)->id,
                $group->values(),
            ))
            ->values();
    }

    private function record(int $accountId, ProviderRunResultDTO $result, int $count): void
    {
        $this->usage->recordApifyCall(new RecordApifyUsageDTO(
            $accountId,
            $result->actorId,
            $result->runId,
            $count,
            $result->costUsd,
        ));
    }

    private function competitor(int $accountId, int $competitorId): Competitor
    {
        return $this->competitors->findById($competitorId, $accountId)
            ?? throw CompetitorNotFoundException::withId($competitorId);
    }
}
