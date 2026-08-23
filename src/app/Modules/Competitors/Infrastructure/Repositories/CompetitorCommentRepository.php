<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Infrastructure\Repositories;

use App\Modules\Competitors\Application\DTO\CompetitorCommentDTO;
use App\Modules\Competitors\Domain\Contracts\CompetitorCommentRepositoryInterface;
use App\Modules\Competitors\Domain\Exceptions\CompetitorPersistenceFailedException;
use App\Modules\Competitors\Infrastructure\Persistence\CompetitorComment;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Throwable;

readonly class CompetitorCommentRepository implements CompetitorCommentRepositoryInterface
{
    private const array UPSERT_COLUMNS = ['author', 'text', 'likes', 'posted_at'];

    public function __construct(private CompetitorComment $model) {}

    public function upsertMany(int $accountId, int $postId, Collection $comments): Collection
    {
        if ($comments->isEmpty()) {
            return collect();
        }

        try {
            $this->model->newQuery()->upsert(
                $comments->map(static fn (CompetitorCommentDTO $comment): array => self::row($accountId, $postId, $comment))->all(),
                ['competitor_post_id', 'external_id'],
                self::UPSERT_COLUMNS,
            );
        } catch (Throwable $exception) {
            throw CompetitorPersistenceFailedException::wrap($exception, context: [
                'account_id' => $accountId,
                'competitor_post_id' => $postId,
                'comments' => $comments->count(),
            ]);
        }

        return $this->model->newQuery()
            ->where('account_id', $accountId)
            ->where('competitor_post_id', $postId)
            ->whereIn('external_id', $comments->pluck('externalId')->all())
            ->get();
    }

    public function forPost(int $accountId, int $postId, int $limit): Collection
    {
        return $this->model->newQuery()
            ->where('account_id', $accountId)
            ->where('competitor_post_id', $postId)
            ->orderByDesc('likes')
            ->limit($limit)
            ->get();
    }

    public function miningCandidates(int $accountId, int $competitorId, int $minimumLength, int $limit): Collection
    {
        return $this->model->newQuery()
            ->where('account_id', $accountId)
            ->whereIn('competitor_post_id', static fn (QueryBuilder $query) => $query
                ->select('id')
                ->from('competitor_posts')
                ->where('account_id', $accountId)
                ->where('competitor_id', $competitorId))
            ->whereRaw('LENGTH(text) >= ?', [$minimumLength])
            ->where('text', 'not like', '%http%')
            ->orderByDesc('posted_at')
            ->limit($limit)
            ->get();
    }

    /** @return array<string, mixed> */
    private static function row(int $accountId, int $postId, CompetitorCommentDTO $comment): array
    {
        return [
            'account_id' => $accountId,
            'competitor_post_id' => $postId,
            'external_id' => $comment->externalId,
            'author' => $comment->author,
            'text' => $comment->text,
            'likes' => $comment->likes,
            'posted_at' => $comment->postedAt?->toDateTimeString(),
        ];
    }
}
