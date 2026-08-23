<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Application\Services;

use App\Modules\Ai\Application\DTO\AiRequestDTO;
use App\Modules\Ai\Application\Services\AiService;
use App\Modules\Ai\Application\Services\AnalysisCacheService;
use App\Modules\Ai\Domain\Enums\AiTask;
use App\Modules\Competitors\Application\DTO\CreateInsightDTO;
use App\Modules\Competitors\Domain\Contracts\CompetitorPostRepositoryInterface;
use App\Modules\Competitors\Domain\Contracts\CompetitorRepositoryInterface;
use App\Modules\Competitors\Domain\Enums\InsightKind;
use App\Modules\Competitors\Domain\Enums\InsightSource;
use App\Modules\Competitors\Infrastructure\Persistence\Competitor;
use App\Modules\Competitors\Infrastructure\Persistence\CompetitorPost;
use App\Modules\Competitors\Infrastructure\Persistence\Insight;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * What is working for a competitor, expressed as insights. The model never sees the post
 * table: it sees per-format averages and a handful of excerpts, which is the whole input
 * it needs to name a pattern.
 */
readonly class CompetitorPatternService
{
    private const int TOP_POSTS = 15;

    private const int CAPTION_EXCERPT = 240;

    private const string CACHE_KIND = 'competitor_patterns';

    private const string PROMPT = 'Below is how one competitor performs. Name the repeatable patterns behind '
        .'their best posts: formats, hook styles and topics that correlate with engagement. '
        .'Return only patterns the numbers support, each with a score from 0 to 1 for how strongly '
        .'the evidence backs it.';

    private const array SCHEMA = [
        'type' => 'object',
        'properties' => [
            'patterns' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'body' => ['type' => 'string'],
                        'score' => ['type' => 'number'],
                    ],
                    'required' => ['title', 'body'],
                ],
            ],
        ],
        'required' => ['patterns'],
        'additionalProperties' => false,
    ];

    public function __construct(
        private CompetitorRepositoryInterface $competitors,
        private CompetitorPostRepositoryInterface $posts,
        private InsightService $insights,
        private AiService $ai,
        private AnalysisCacheService $cache,
    ) {}

    /**
     * @return Collection<int, Insight>
     */
    public function extract(int $accountId, int $competitorId): Collection
    {
        $competitor = $this->competitors->findById($competitorId, $accountId);
        $top = $this->posts->topByEngagement($accountId, $competitorId, self::TOP_POSTS);

        if ($competitor === null || $top->isEmpty()) {
            return collect();
        }

        return $this->persist(
            $competitor,
            $this->analyse($accountId, $competitor, self::input($top, $this->posts->engagementByType($accountId, $competitorId))),
        );
    }

    /**
     * @param  array<string, mixed>  $judged
     * @return Collection<int, Insight>
     */
    private function persist(Competitor $competitor, array $judged): Collection
    {
        return collect($judged['patterns'] ?? [])
            ->filter(static fn (mixed $pattern): bool => is_array($pattern)
                && is_string($pattern['title'] ?? null)
                && is_string($pattern['body'] ?? null))
            ->map(fn (array $pattern): Insight => $this->insights->record(new CreateInsightDTO(
                (int) $competitor->account_id,
                InsightKind::Pattern,
                InsightSource::CompetitorAnalysis,
                $pattern['title'],
                $pattern['body'],
                ['handle' => $competitor->handle, 'platform' => $competitor->platform->value],
                (float) ($pattern['score'] ?? 0),
                $competitor->strategy_id,
                (int) $competitor->id,
            )))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function analyse(int $accountId, Competitor $competitor, array $input): array
    {
        return $this->cache->remember($accountId, self::CACHE_KIND, $input, fn (): array => $this->ai->structured(
            new AiRequestDTO(
                $accountId,
                AiTask::InsightExtraction,
                self::PROMPT,
                $input,
                strategyId: $competitor->strategy_id,
            ),
            self::SCHEMA,
        ));
    }

    /**
     * @param  Collection<int, CompetitorPost>  $top
     * @param  Collection<int, array<string, mixed>>  $byType
     * @return array<string, mixed>
     */
    private static function input(Collection $top, Collection $byType): array
    {
        return [
            'engagement_by_type' => $byType->all(),
            'top_posts' => $top->map(static fn (CompetitorPost $post): array => [
                'type' => $post->type,
                'caption' => Str::limit((string) $post->caption, self::CAPTION_EXCERPT),
                'likes' => $post->likes,
                'comments' => $post->comments_count,
                'views' => $post->views,
                'posted_at' => $post->posted_at?->toDateString(),
            ])->values()->all(),
        ];
    }
}
