<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Application\Services;

use App\Modules\Ai\Application\DTO\AiRequestDTO;
use App\Modules\Ai\Application\Services\AiService;
use App\Modules\Ai\Application\Services\AnalysisCacheService;
use App\Modules\Ai\Domain\Enums\AiTask;
use App\Modules\Competitors\Application\DTO\CommentClusterDTO;
use App\Modules\Competitors\Application\DTO\CreateInsightDTO;
use App\Modules\Competitors\Domain\Contracts\CompetitorCommentRepositoryInterface;
use App\Modules\Competitors\Domain\Contracts\RefutedHypothesisSourceInterface;
use App\Modules\Competitors\Domain\Enums\InsightKind;
use App\Modules\Competitors\Domain\Enums\InsightSource;
use App\Modules\Competitors\Domain\Support\CommentClusterer;
use App\Modules\Competitors\Domain\Support\CommentNormaliser;
use App\Modules\Competitors\Infrastructure\Persistence\Insight;
use App\Modules\Settings\Application\Services\SettingsService;
use App\Modules\Strategies\Application\DTO\StrategyFilterDTO;
use App\Modules\Strategies\Application\Services\StrategyService;
use App\Modules\Strategies\Domain\Enums\StrategyStatus;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Illuminate\Support\Collection;

/**
 * Comments in, content ideas out, through three filters in a fixed order.
 *
 * 1. Recurrence — SQL and string heuristics. The same question from different people.
 * 2. Novelty — SQL. Not an idea already captured, not a hypothesis already refuted.
 * 3. Alignment — the model, and only the model, because "does this fit the strategy" is
 *    the one question a regular expression cannot answer.
 *
 * The order is the point: by the time a token is spent, the input is a dozen clustered
 * topics with counts, never a dump of raw comments.
 */
readonly class CommentMiningService
{
    private const string FEATURE_FLAG = 'features.comment_mining';

    /** Shorter than this is a reaction, not a question worth building a video around. */
    private const int MINIMUM_COMMENT_LENGTH = 20;

    private const int CANDIDATE_LIMIT = 500;

    private const int MINIMUM_SHARED_KEYWORDS = 2;

    private const int MINIMUM_DISTINCT_AUTHORS = 3;

    private const int TOPICS_TO_JUDGE = 12;

    private const string CACHE_KIND = 'comment_mining';

    private const string PROMPT = 'Below are recurring topics mined from the comments of a competitor, each '
        .'with how many people raised it, and the active strategies of this account. Decide which topics are '
        .'worth turning into content for one of those strategies, respecting its objective and constraints. '
        .'Drop anything that fits none of them. For each idea you keep, reference the topic by its index and '
        .'the strategy by its id, and score it from 0 to 1.';

    private const array SCHEMA = [
        'type' => 'object',
        'properties' => [
            'ideas' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'topic_index' => ['type' => 'integer'],
                        'strategy_id' => ['type' => 'integer'],
                        'title' => ['type' => 'string'],
                        'body' => ['type' => 'string'],
                        'score' => ['type' => 'number'],
                    ],
                    'required' => ['topic_index', 'title', 'body'],
                ],
            ],
        ],
        'required' => ['ideas'],
        'additionalProperties' => false,
    ];

    public function __construct(
        private CompetitorCommentRepositoryInterface $comments,
        private InsightService $insights,
        private RefutedHypothesisSourceInterface $refuted,
        private CommentClusterer $clusterer,
        private StrategyService $strategies,
        private SettingsService $settings,
        private AiService $ai,
        private AnalysisCacheService $cache,
    ) {}

    /**
     * @return Collection<int, Insight>
     */
    public function mine(int $accountId, int $competitorId): Collection
    {
        if ($this->settings->get(self::FEATURE_FLAG, $accountId) !== true) {
            return collect();
        }

        $strategies = $this->activeStrategies($accountId);

        if ($strategies->isEmpty()) {
            return collect();
        }

        $clusters = $this->novel($accountId, $this->recurring($accountId, $competitorId));

        return $clusters->isEmpty()
            ? collect()
            : $this->persist($accountId, $competitorId, $strategies, $clusters, $this->judge($accountId, $strategies, $clusters));
    }

    /**
     * Filter one. Nothing here costs a token: the database drops link spam and one-liners,
     * then keyword clustering counts how many different people raised each topic.
     *
     * @return Collection<int, CommentClusterDTO>
     */
    private function recurring(int $accountId, int $competitorId): Collection
    {
        return $this->clusterer->cluster(
            $this->comments->miningCandidates($accountId, $competitorId, self::MINIMUM_COMMENT_LENGTH, self::CANDIDATE_LIMIT),
            self::MINIMUM_SHARED_KEYWORDS,
            self::MINIMUM_DISTINCT_AUTHORS,
        );
    }

    /**
     * Filter two. An idea already captured, or a hypothesis the account already refuted,
     * is not an idea — it is the same money spent twice.
     *
     * @param  Collection<int, CommentClusterDTO>  $clusters
     * @return Collection<int, CommentClusterDTO>
     */
    private function novel(int $accountId, Collection $clusters): Collection
    {
        $known = $this->insights->titlesOfKind($accountId, InsightKind::ContentIdea)
            ->merge($this->refuted->refutedHypotheses($accountId));

        return $clusters
            ->reject(static fn (CommentClusterDTO $cluster): bool => $known->contains(
                static fn (string $text): bool => CommentNormaliser::overlaps(
                    $cluster->keywords,
                    $text,
                    self::MINIMUM_SHARED_KEYWORDS,
                ),
            ))
            ->take(self::TOPICS_TO_JUDGE)
            ->values();
    }

    /**
     * Filter three, and the only one that costs anything.
     *
     * @param  Collection<int, Strategy>  $strategies
     * @param  Collection<int, CommentClusterDTO>  $clusters
     * @return array<string, mixed>
     */
    private function judge(int $accountId, Collection $strategies, Collection $clusters): array
    {
        $input = self::input($strategies, $clusters);

        return $this->cache->remember($accountId, self::CACHE_KIND, $input, fn (): array => $this->ai->structured(
            new AiRequestDTO($accountId, AiTask::CommentMining, self::PROMPT, $input),
            self::SCHEMA,
        ));
    }

    /**
     * @param  Collection<int, Strategy>  $strategies
     * @param  Collection<int, CommentClusterDTO>  $clusters
     * @param  array<string, mixed>  $judged
     * @return Collection<int, Insight>
     */
    private function persist(
        int $accountId,
        int $competitorId,
        Collection $strategies,
        Collection $clusters,
        array $judged,
    ): Collection {
        $strategyIds = $strategies->pluck('id')->map(static fn (mixed $id): int => (int) $id);

        return collect($judged['ideas'] ?? [])
            ->filter(static fn (mixed $idea): bool => self::isUsable($idea) && $clusters->has($idea['topic_index']))
            ->map(fn (array $idea): Insight => $this->insights->record(new CreateInsightDTO(
                $accountId,
                InsightKind::ContentIdea,
                InsightSource::CommentMining,
                $idea['title'],
                $idea['body'],
                self::evidence($clusters->get($idea['topic_index'])),
                (float) ($idea['score'] ?? 0),
                $strategyIds->contains($idea['strategy_id'] ?? null) ? (int) $idea['strategy_id'] : null,
                $competitorId,
            )))
            ->values();
    }

    /**
     * @return Collection<int, Strategy>
     */
    private function activeStrategies(int $accountId): Collection
    {
        return collect($this->strategies->forAccount(new StrategyFilterDTO($accountId, StrategyStatus::Active)));
    }

    private static function isUsable(mixed $idea): bool
    {
        return is_array($idea)
            && is_int($idea['topic_index'] ?? null)
            && is_string($idea['title'] ?? null)
            && is_string($idea['body'] ?? null);
    }

    /**
     * The evidence is the point: an idea nobody can trace back to real comments is an
     * opinion the model had.
     *
     * @return array<string, mixed>
     */
    private static function evidence(CommentClusterDTO $cluster): array
    {
        return [
            'topic' => $cluster->topic(),
            'frequency' => $cluster->frequency,
            'distinct_authors' => $cluster->distinctAuthors,
            'comment_ids' => $cluster->commentIds,
            'samples' => $cluster->samples,
        ];
    }

    /**
     * @param  Collection<int, Strategy>  $strategies
     * @param  Collection<int, CommentClusterDTO>  $clusters
     * @return array<string, mixed>
     */
    private static function input(Collection $strategies, Collection $clusters): array
    {
        return [
            'strategies' => $strategies->map(static fn (Strategy $strategy): array => [
                'id' => (int) $strategy->id,
                'objective' => $strategy->objective,
                'north_star_metric' => $strategy->north_star_metric,
                'constraints' => $strategy->constraints,
            ])->values()->all(),
            'topics' => $clusters->map(static fn (CommentClusterDTO $cluster, int $index): array => [
                'index' => $index,
                'topic' => $cluster->topic(),
                'frequency' => $cluster->frequency,
                'distinct_authors' => $cluster->distinctAuthors,
                'samples' => $cluster->samples,
            ])->values()->all(),
        ];
    }
}
