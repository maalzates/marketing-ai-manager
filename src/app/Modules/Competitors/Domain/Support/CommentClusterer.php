<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Domain\Support;

use App\Modules\Competitors\Application\DTO\CommentClusterDTO;
use App\Modules\Competitors\Infrastructure\Persistence\CompetitorComment;
use Illuminate\Support\Collection;

/**
 * Recurrence, filter one of three, resolved without a model: comments that share enough
 * keywords land in the same group, and a group only survives if enough different people
 * are in it. One person asking five times is not a recurring topic.
 */
readonly class CommentClusterer
{
    private const int MINIMUM_KEYWORDS = 2;

    private const int KEYWORDS_PER_TOPIC = 4;

    private const int SAMPLES_PER_CLUSTER = 3;

    /**
     * @param  Collection<int, CompetitorComment>  $comments
     * @return Collection<int, CommentClusterDTO>
     */
    public function cluster(Collection $comments, int $minimumSharedKeywords, int $minimumDistinctAuthors): Collection
    {
        return self::group($this->meaningful($comments), $minimumSharedKeywords)
            ->map(static fn (Collection $members): CommentClusterDTO => self::describe($members))
            ->filter(static fn (CommentClusterDTO $cluster): bool => $cluster->distinctAuthors >= $minimumDistinctAuthors)
            ->sortByDesc(static fn (CommentClusterDTO $cluster): int => $cluster->distinctAuthors)
            ->values();
    }

    /**
     * @param  Collection<int, CompetitorComment>  $comments
     * @return Collection<int, array{id: int, author: string, text: string, keywords: list<string>}>
     */
    private function meaningful(Collection $comments): Collection
    {
        return $comments
            ->reject(static fn (CompetitorComment $comment): bool => CommentNormaliser::isSpam($comment->text)
                || CommentNormaliser::isTrivial($comment->text))
            ->map(static fn (CompetitorComment $comment): array => [
                'id' => (int) $comment->id,
                'author' => (string) $comment->author,
                'text' => $comment->text,
                'keywords' => CommentNormaliser::keywords($comment->text),
            ])
            ->reject(static fn (array $entry): bool => count($entry['keywords']) < self::MINIMUM_KEYWORDS)
            ->unique(static fn (array $entry): string => $entry['author'].'|'.implode('-', $entry['keywords']))
            ->values();
    }

    /**
     * @param  Collection<int, array{id: int, author: string, text: string, keywords: list<string>}>  $entries
     * @return Collection<int, Collection<int, array{id: int, author: string, text: string, keywords: list<string>}>>
     */
    private static function group(Collection $entries, int $minimumSharedKeywords): Collection
    {
        return $entries->reduce(
            static function (Collection $clusters, array $entry) use ($minimumSharedKeywords): Collection {
                $index = $clusters->search(static fn (Collection $members): bool => count(array_intersect(
                    $members->first()['keywords'],
                    $entry['keywords'],
                )) >= $minimumSharedKeywords);

                return $index === false
                    ? $clusters->push(collect([$entry]))
                    : $clusters->put($index, $clusters->get($index)->push($entry));
            },
            collect(),
        );
    }

    /**
     * @param  Collection<int, array{id: int, author: string, text: string, keywords: list<string>}>  $members
     */
    private static function describe(Collection $members): CommentClusterDTO
    {
        return new CommentClusterDTO(
            self::dominantKeywords($members),
            $members->count(),
            $members->pluck('author')->unique()->count(),
            $members->pluck('id')->all(),
            $members->pluck('text')->take(self::SAMPLES_PER_CLUSTER)->values()->all(),
        );
    }

    /**
     * @param  Collection<int, array{id: int, author: string, text: string, keywords: list<string>}>  $members
     * @return list<string>
     */
    private static function dominantKeywords(Collection $members): array
    {
        return $members
            ->flatMap(static fn (array $entry): array => $entry['keywords'])
            ->countBy()
            ->sortDesc()
            ->take(self::KEYWORDS_PER_TOPIC)
            ->keys()
            ->all();
    }
}
