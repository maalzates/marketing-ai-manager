<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Domain\Support;

use Illuminate\Support\Str;

/**
 * The cheap half of comment mining. Everything here is deterministic string work, because
 * a model that is asked to tell an emoji apart from a question is being paid to do what a
 * regular expression already does.
 */
readonly class CommentNormaliser
{
    private const int MINIMUM_KEYWORD_LENGTH = 4;

    private const int MINIMUM_WORDS = 3;

    private const int MAXIMUM_MENTIONS = 2;

    /** @var list<string> */
    private const array PROMOTIONAL_FRAGMENTS = [
        'link in bio', 'dm me', 'check my', 'follow me', 'follow back', 'promo code',
        'sigueme', 'escribeme', 'link en la bio', 'gana dinero', 'te sigo', 'sigo de vuelta',
    ];

    /** Longest first: "ediciones" has to lose "ciones" before it can lose "es". */
    private const array SUFFIXES = ['ciones', 'iendo', 'ando', 'cion', 'ings', 'ing', 'ed', 'es', 's'];

    /** @var list<string> */
    private const array STOPWORDS = [
        'the', 'and', 'for', 'you', 'your', 'yours', 'this', 'that', 'with', 'from', 'have',
        'has', 'had', 'are', 'was', 'were', 'been', 'what', 'when', 'where', 'which', 'who',
        'how', 'why', 'can', 'could', 'would', 'should', 'about', 'there', 'their', 'they',
        'them', 'then', 'than', 'just', 'like', 'love', 'very', 'much', 'more', 'most',
        'some', 'any', 'all', 'not', 'but', 'out', 'into', 'over', 'also', 'because',
        'que', 'los', 'las', 'una', 'uno', 'unos', 'unas', 'del', 'con', 'por', 'para',
        'como', 'donde', 'cuando', 'porque', 'pero', 'esta', 'este', 'esto', 'esos', 'esas',
        'muy', 'mas', 'todo', 'toda', 'todos', 'todas', 'nada', 'algo', 'hay', 'son', 'ser',
        'estoy', 'eres', 'tiene', 'tienen', 'hace', 'hacer', 'gracias', 'hola', 'bueno',
    ];

    public static function normalise(string $text): string
    {
        return trim((string) preg_replace(
            ['/https?:\/\/\S+/u', '/[@#]\S+/u', '/[^a-z0-9\s]/', '/\s+/'],
            ['', '', ' ', ' '],
            Str::lower(Str::ascii($text)),
        ));
    }

    /** @return list<string> */
    public static function keywords(string $text): array
    {
        return collect(explode(' ', self::normalise($text)))
            ->filter(static fn (string $word): bool => strlen($word) >= self::MINIMUM_KEYWORD_LENGTH)
            ->reject(static fn (string $word): bool => in_array($word, self::STOPWORDS, true))
            ->map(static fn (string $word): string => self::stem($word))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Crude, deliberately: "reels", "reel", "editing" and "edited" are the same topic, and
     * a real stemmer is a dependency to buy a rounding error of extra accuracy.
     */
    private static function stem(string $word): string
    {
        foreach (self::SUFFIXES as $suffix) {
            if (str_ends_with($word, $suffix) && strlen($word) - strlen($suffix) >= self::MINIMUM_KEYWORD_LENGTH) {
                return substr($word, 0, -strlen($suffix));
            }
        }

        return $word;
    }

    public static function isSpam(string $text): bool
    {
        return Str::contains($text, ['http://', 'https://'])
            || substr_count($text, '@') > self::MAXIMUM_MENTIONS
            || Str::contains(Str::lower(Str::ascii($text)), self::PROMOTIONAL_FRAGMENTS);
    }

    /** An emoji, a name tag or "🔥🔥🔥" is engagement, not a topic anyone can build on. */
    public static function isTrivial(string $text): bool
    {
        return count(array_filter(explode(' ', self::normalise($text)))) < self::MINIMUM_WORDS;
    }

    /** @param list<string> $keywords */
    public static function overlaps(array $keywords, string $candidate, int $minimumShared): bool
    {
        return count(array_intersect($keywords, self::keywords($candidate))) >= $minimumShared;
    }
}
