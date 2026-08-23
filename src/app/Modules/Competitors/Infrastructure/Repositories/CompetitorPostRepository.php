<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Infrastructure\Repositories;

use App\Modules\Competitors\Application\DTO\CompetitorPostDTO;
use App\Modules\Competitors\Application\DTO\CompetitorPostFilterDTO;
use App\Modules\Competitors\Domain\Contracts\CompetitorPostRepositoryInterface;
use App\Modules\Competitors\Domain\Enums\Sentiment;
use App\Modules\Competitors\Domain\Exceptions\CompetitorPersistenceFailedException;
use App\Modules\Competitors\Infrastructure\Persistence\CompetitorPost;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

readonly class CompetitorPostRepository implements CompetitorPostRepositoryInterface
{
    /** Sentiment is deliberately absent: a re-sync refreshes metrics, not the analysis. */
    private const array UPSERT_COLUMNS = [
        'url', 'type', 'caption', 'posted_at', 'likes', 'comments_count', 'views', 'engagement_rate', 'raw',
    ];

    private const string ENGAGEMENT_EXPRESSION = 'COALESCE(likes, 0) + comments_count';

    public function __construct(private CompetitorPost $model) {}

    public function findAll(CompetitorPostFilterDTO $filters): Collection|LengthAwarePaginator
    {
        return $filters->perPage > 0
            ? $this->query($filters)->paginate(perPage: $filters->perPage, page: $filters->page)
            : $this->query($filters)->get();
    }

    public function upsertMany(int $accountId, int $competitorId, Collection $posts): Collection
    {
        if ($posts->isEmpty()) {
            return collect();
        }

        try {
            $this->model->newQuery()->upsert(
                $posts->map(static fn (CompetitorPostDTO $post): array => self::row($accountId, $competitorId, $post))->all(),
                ['competitor_id', 'external_id'],
                self::UPSERT_COLUMNS,
            );
        } catch (Throwable $exception) {
            throw CompetitorPersistenceFailedException::wrap($exception, context: [
                'account_id' => $accountId,
                'competitor_id' => $competitorId,
                'posts' => $posts->count(),
            ]);
        }

        return $this->model->newQuery()
            ->where('account_id', $accountId)
            ->where('competitor_id', $competitorId)
            ->whereIn('external_id', $posts->pluck('externalId')->all())
            ->get();
    }

    public function topByEngagement(int $accountId, int $competitorId, int $limit): Collection
    {
        return $this->scoped($accountId, $competitorId)
            ->orderByRaw(self::ENGAGEMENT_EXPRESSION.' desc')
            ->limit($limit)
            ->get();
    }

    public function withComments(int $accountId, int $competitorId, int $limit): Collection
    {
        return $this->scoped($accountId, $competitorId)
            ->where('comments_count', '>', 0)
            ->orderByDesc('posted_at')
            ->limit($limit)
            ->get();
    }

    public function awaitingSentiment(int $accountId, int $competitorId, int $limit): Collection
    {
        return $this->scoped($accountId, $competitorId)
            ->whereNull('sentiment')
            ->where('comments_count', '>', 0)
            ->orderByDesc('posted_at')
            ->limit($limit)
            ->get();
    }

    public function storeSentiment(CompetitorPost $post, Sentiment $sentiment, array $summary): CompetitorPost
    {
        try {
            $post->update(['sentiment' => $sentiment, 'sentiment_summary' => $summary]);

            return $post->refresh();
        } catch (Throwable $exception) {
            throw CompetitorPersistenceFailedException::wrap($exception, context: [
                'competitor_post_id' => $post->id,
                'sentiment' => $sentiment->value,
            ]);
        }
    }

    public function engagementByType(int $accountId, int $competitorId): Collection
    {
        return $this->scoped($accountId, $competitorId)
            ->selectRaw('type, COUNT(*) as posts, AVG(likes) as average_likes, AVG(comments_count) as average_comments, AVG(views) as average_views')
            ->groupBy('type')
            ->get()
            ->map(static fn (CompetitorPost $row): array => [
                'type' => $row->type,
                'posts' => (int) $row->getAttribute('posts'),
                // AVG ignores NULL, so a profile that hides likes reports no average
                // instead of an average dragged to zero.
                'average_likes' => self::rounded($row->getAttribute('average_likes')),
                'average_comments' => self::rounded($row->getAttribute('average_comments')),
                'average_views' => self::rounded($row->getAttribute('average_views')),
            ]);
    }

    /** @return array<string, mixed> */
    private static function row(int $accountId, int $competitorId, CompetitorPostDTO $post): array
    {
        return [
            'account_id' => $accountId,
            'competitor_id' => $competitorId,
            'external_id' => $post->externalId,
            'url' => $post->url,
            'type' => $post->type,
            'caption' => $post->caption,
            'posted_at' => $post->postedAt?->toDateTimeString(),
            'likes' => $post->likes,
            'comments_count' => $post->commentsCount,
            'views' => $post->views,
            'engagement_rate' => self::engagementRate($post),
            'raw' => json_encode($post->raw),
        ];
    }

    /** Unknown likes or no impressions to divide by means no rate, not a rate of zero. */
    private static function engagementRate(CompetitorPostDTO $post): ?float
    {
        return $post->views > 0 && $post->likes !== null
            ? round(($post->likes + $post->commentsCount) / $post->views, 4)
            : null;
    }

    private static function rounded(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 2);
    }

    private function scoped(int $accountId, int $competitorId): Builder
    {
        return $this->model->newQuery()
            ->where('account_id', $accountId)
            ->where('competitor_id', $competitorId);
    }

    private function query(CompetitorPostFilterDTO $filters): Builder
    {
        return $this->scoped($filters->accountId, $filters->competitorId)
            ->when($filters->sentiment, fn (Builder $query, Sentiment $sentiment): Builder => $query->where('sentiment', $sentiment->value))
            ->orderByDesc('posted_at')
            ->orderByDesc('id');
    }
}
