<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Application\DTO;

/**
 * A recurring topic found by SQL and heuristics, before any model has seen it. Frequency
 * counts comments; distinctAuthors counts people, and only the second one decides whether
 * an idea is worth many viewers instead of one.
 */
readonly class CommentClusterDTO
{
    /**
     * @param  list<string>  $keywords
     * @param  list<int>  $commentIds
     * @param  list<string>  $samples
     */
    public function __construct(
        public array $keywords,
        public int $frequency,
        public int $distinctAuthors,
        public array $commentIds,
        public array $samples,
    ) {}

    public function topic(): string
    {
        return implode(' ', $this->keywords);
    }
}
