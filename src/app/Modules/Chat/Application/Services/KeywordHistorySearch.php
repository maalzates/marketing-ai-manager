<?php

declare(strict_types=1);

namespace App\Modules\Chat\Application\Services;

use App\Modules\Chat\Application\DTO\HistorySearchDTO;
use App\Modules\Chat\Domain\Contracts\HistorySearchInterface;
use App\Modules\Experiments\Application\DTO\ExperimentFilterDTO;
use App\Modules\Experiments\Application\Services\ExperimentService;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Layer 3 of the memory in `core.md` §4, in its lexical form: the account's own experiments
 * and verdicts matched by term. It is deliberately the whole of the implementation behind
 * HistorySearchInterface, so the embedding-backed version replaces this class and nothing
 * else — the tool, the loop and the prompt stay untouched.
 */
readonly class KeywordHistorySearch implements HistorySearchInterface
{
    private const int MINIMUM_TERM_LENGTH = 3;

    public function __construct(private ExperimentService $experiments) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function search(HistorySearchDTO $dto): Collection
    {
        return collect($this->experiments->forStrategy(
            new ExperimentFilterDTO($dto->accountId, $dto->strategyId, null, null, null, 0, 1),
        ))
            ->filter(static fn (Experiment $experiment): bool => self::matches($experiment, self::terms($dto->query)))
            ->take($dto->limit)
            ->map(static fn (Experiment $experiment): array => self::summarise($experiment))
            ->values();
    }

    /**
     * @param  list<string>  $terms
     */
    private static function matches(Experiment $experiment, array $terms): bool
    {
        return $terms === [] || Str::contains(
            Str::lower(implode(' ', [
                $experiment->title,
                $experiment->hypothesis,
                $experiment->verdict_reason,
                $experiment->verdict?->value,
            ])),
            $terms,
        );
    }

    /** @return list<string> */
    private static function terms(string $query): array
    {
        return Str::of($query)
            ->lower()
            ->split('/\W+/u')
            ->filter(static fn (string $term): bool => strlen($term) >= self::MINIMUM_TERM_LENGTH)
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private static function summarise(Experiment $experiment): array
    {
        return [
            'code' => $experiment->code,
            'title' => $experiment->title,
            'hypothesis' => $experiment->hypothesis,
            'type' => $experiment->type->value,
            'platform' => $experiment->platform->value,
            'status' => $experiment->status->value,
            'verdict' => $experiment->verdict?->value,
            'verdict_reason' => $experiment->verdict_reason,
            'starts_at' => $experiment->starts_at?->toDateString(),
            'ends_at' => $experiment->ends_at?->toDateString(),
        ];
    }
}
