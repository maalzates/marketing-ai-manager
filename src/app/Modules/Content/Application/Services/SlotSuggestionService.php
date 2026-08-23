<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Services;

use App\Modules\Content\Application\DTO\ScheduleFilterDTO;
use App\Modules\Content\Domain\Contracts\ContentScheduleRepositoryInterface;
use App\Modules\Content\Domain\Enums\ScheduleStatus;
use App\Modules\Content\Infrastructure\Persistence\ContentSchedule;
use App\Modules\Experiments\Application\Services\ExperimentService;
use App\Modules\Experiments\Infrastructure\Persistence\ExperimentMetric;
use App\Modules\Strategies\Application\Services\StrategyService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Proposes the hours for the next pieces. The ranking is the account's own engagement per hour
 * of the day; the strategy's configured hours decide what is even on the table, so a suggestion
 * can never drag a brand outside the cadence its owner set.
 */
readonly class SlotSuggestionService
{
    /** Read when a strategy declares no preferred hours and there is no history to learn from. */
    private const array FALLBACK_HOURS = [9, 13, 19];

    private const int HISTORY_SAMPLE = 20;

    private const int DAYS_PER_WEEK = 7;

    public function __construct(
        private ContentScheduleRepositoryInterface $schedules,
        private StrategyService $strategies,
        private ExperimentService $experiments,
    ) {}

    /**
     * @return Collection<int, CarbonImmutable>
     */
    public function suggest(int $accountId, int $strategyId, int $count, CarbonImmutable $from): Collection
    {
        $cadence = $this->strategies->find($strategyId, $accountId)->organic_cadence;
        $hours = $this->rankedHours($accountId, $cadence['preferred_hours'] ?? []);
        $taken = $this->takenSlots($accountId, $from);

        // One piece per cadence day, cycling through the ranked hours, so a batch of suggestions
        // spreads over the calendar instead of stacking on the first free morning.
        return collect(range(0, $count * self::DAYS_PER_WEEK))
            ->map(fn (int $day): CarbonImmutable => $from
                ->addDays($day * self::spacing($cadence))
                ->setTime($hours[$day % count($hours)], 0))
            ->reject(fn (CarbonImmutable $slot): bool => $slot->isBefore($from) || $taken->contains($slot->format('Y-m-d H')))
            ->take($count)
            ->values();
    }

    /**
     * @param  list<int>  $preferred
     * @return list<int>
     */
    private function rankedHours(int $accountId, array $preferred): array
    {
        $byEngagement = $this->historicalHours($accountId);
        $candidates = $preferred === [] ? $byEngagement : $preferred;

        return $candidates === []
            ? self::FALLBACK_HOURS
            : collect($candidates)
                ->sortBy(fn (int $hour): int => self::rankOf($hour, $byEngagement))
                ->values()
                ->all();
    }

    /** @param  list<int>  $byEngagement */
    private static function rankOf(int $hour, array $byEngagement): int
    {
        return ($position = array_search($hour, $byEngagement, true)) === false ? PHP_INT_MAX : $position;
    }

    /**
     * Hours of the day the account's own published pieces earned the most engagement, best first.
     *
     * @return list<int>
     */
    private function historicalHours(int $accountId): array
    {
        return $this->schedules
            ->findAll(new ScheduleFilterDTO($accountId, null, ScheduleStatus::Published, null, null, null, 0, 1))
            ->take(self::HISTORY_SAMPLE)
            ->groupBy(fn (ContentSchedule $schedule): int => (int) $schedule->published_at?->hour)
            ->map(fn (Collection $group): int => $group->sum(
                fn (ContentSchedule $schedule): int => $this->engagementOf($schedule, $accountId),
            ))
            ->sortDesc()
            ->keys()
            ->all();
    }

    private function engagementOf(ContentSchedule $schedule, int $accountId): int
    {
        return (int) $this->experiments
            ->metricsFor((int) $schedule->experiment_id, $accountId)
            ->sum(fn (ExperimentMetric $metric): int => (int) $metric->engagement);
    }

    /**
     * @return Collection<int, string>
     */
    private function takenSlots(int $accountId, CarbonImmutable $from): Collection
    {
        return $this->schedules
            ->findAll(new ScheduleFilterDTO($accountId, null, null, null, $from, null, 0, 1))
            ->map(fn (ContentSchedule $schedule): string => $schedule->scheduled_at->format('Y-m-d H'))
            ->values();
    }

    private static function spacing(array $cadence): int
    {
        return max(1, intdiv(self::DAYS_PER_WEEK, max(1, (int) ($cadence['posts_per_week'] ?? 1))));
    }
}
