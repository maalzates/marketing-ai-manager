<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Application\Services;

use App\Modules\Ai\Application\DTO\AiRequestDTO;
use App\Modules\Ai\Application\Services\AiService;
use App\Modules\Ai\Application\Services\AnalysisCacheService;
use App\Modules\Ai\Domain\Enums\AiTask;
use App\Modules\Competitors\Domain\Contracts\CompetitorCommentRepositoryInterface;
use App\Modules\Competitors\Domain\Contracts\CompetitorPostRepositoryInterface;
use App\Modules\Competitors\Domain\Enums\Sentiment;
use App\Modules\Competitors\Infrastructure\Persistence\CompetitorComment;
use App\Modules\Competitors\Infrastructure\Persistence\CompetitorPost;
use Illuminate\Support\Collection;

/**
 * One call per post, not one per comment. Engagement volume says how loud a post was;
 * this says what the noise was about.
 */
readonly class CommentSentimentService
{
    private const int POSTS_PER_RUN = 10;

    private const int COMMENTS_PER_BATCH = 100;

    private const string CACHE_KIND = 'comment_sentiment';

    private const string PROMPT = 'Classify the overall reception of one post from the comments below. '
        .'Answer with the dominant sentiment and a summary holding the dominant topics, the rough '
        .'split between positive, negative and neutral comments, and the most telling quotes.';

    private const array SCHEMA = [
        'type' => 'object',
        'properties' => [
            'sentiment' => ['type' => 'string', 'enum' => ['positive', 'negative', 'neutral']],
            'summary' => [
                'type' => 'object',
                'properties' => [
                    'dominant_topics' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'positive' => ['type' => 'integer'],
                    'negative' => ['type' => 'integer'],
                    'neutral' => ['type' => 'integer'],
                    'highlights' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
            ],
        ],
        'required' => ['sentiment', 'summary'],
        'additionalProperties' => false,
    ];

    public function __construct(
        private CompetitorPostRepositoryInterface $posts,
        private CompetitorCommentRepositoryInterface $comments,
        private AiService $ai,
        private AnalysisCacheService $cache,
    ) {}

    /**
     * @return Collection<int, CompetitorPost>
     */
    public function analyse(int $accountId, int $competitorId): Collection
    {
        return $this->posts->awaitingSentiment($accountId, $competitorId, self::POSTS_PER_RUN)
            ->map(fn (CompetitorPost $post): ?CompetitorPost => $this->classify($accountId, $post))
            ->filter()
            ->values();
    }

    private function classify(int $accountId, CompetitorPost $post): ?CompetitorPost
    {
        $comments = $this->comments->forPost($accountId, (int) $post->id, self::COMMENTS_PER_BATCH);

        if ($comments->isEmpty()) {
            return null;
        }

        $judged = $this->judge($accountId, $post, $comments);

        return $this->posts->storeSentiment(
            $post,
            Sentiment::tryFrom((string) ($judged['sentiment'] ?? '')) ?? Sentiment::Neutral,
            (array) ($judged['summary'] ?? []),
        );
    }

    /**
     * @param  Collection<int, CompetitorComment>  $comments
     * @return array<string, mixed>
     */
    private function judge(int $accountId, CompetitorPost $post, Collection $comments): array
    {
        $input = self::input($post, $comments);

        return $this->cache->remember($accountId, self::CACHE_KIND, $input, fn (): array => $this->ai->structured(
            new AiRequestDTO($accountId, AiTask::CommentSentiment, self::PROMPT, $input),
            self::SCHEMA,
        ));
    }

    /**
     * @param  Collection<int, CompetitorComment>  $comments
     * @return array<string, mixed>
     */
    private static function input(CompetitorPost $post, Collection $comments): array
    {
        return [
            'post_external_id' => $post->external_id,
            'comments' => $comments->map(static fn (CompetitorComment $comment): string => $comment->text)->all(),
        ];
    }
}
