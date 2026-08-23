<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Application\Services;

use App\Modules\Competitors\Application\DTO\CompetitorFilterDTO;
use App\Modules\Competitors\Application\DTO\CompetitorPostFilterDTO;
use App\Modules\Competitors\Application\DTO\CreateCompetitorDTO;
use App\Modules\Competitors\Application\Jobs\AnalyseCommentSentimentJob;
use App\Modules\Competitors\Application\Jobs\AnalyseCompetitorPostsJob;
use App\Modules\Competitors\Application\Jobs\MineCommentIdeasJob;
use App\Modules\Competitors\Application\Jobs\SyncCompetitorCommentsJob;
use App\Modules\Competitors\Application\Jobs\SyncCompetitorJob;
use App\Modules\Competitors\Domain\Contracts\CompetitorPostRepositoryInterface;
use App\Modules\Competitors\Domain\Contracts\CompetitorRepositoryInterface;
use App\Modules\Competitors\Domain\Exceptions\CompetitorAlreadyTrackedException;
use App\Modules\Competitors\Domain\Exceptions\CompetitorNotFoundException;
use App\Modules\Competitors\Infrastructure\Persistence\Competitor;
use App\Modules\Competitors\Infrastructure\Persistence\CompetitorPost;
use App\Modules\Settings\Application\Services\SettingsService;
use App\Modules\Strategies\Application\DTO\StrategyFilterDTO;
use App\Modules\Strategies\Application\Services\StrategyService;
use App\Modules\Strategies\Domain\Enums\StrategyStatus;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

readonly class CompetitorService
{
    private const string FEATURE_FLAG = 'features.competitor_analysis';

    public function __construct(
        private CompetitorRepositoryInterface $repository,
        private CompetitorPostRepositoryInterface $posts,
        private StrategyService $strategies,
        private SettingsService $settings,
        private Dispatcher $dispatcher,
    ) {}

    /**
     * @return Collection<int, Competitor>|LengthAwarePaginator<int, Competitor>
     */
    public function forAccount(CompetitorFilterDTO $filters): Collection|LengthAwarePaginator
    {
        return $this->repository->findAll($filters);
    }

    /** A competitor of another account is not forbidden, it does not exist. */
    public function find(int $id, int $accountId): Competitor
    {
        return $this->repository->findById($id, $accountId) ?? throw CompetitorNotFoundException::withId($id);
    }

    public function create(CreateCompetitorDTO $dto): Competitor
    {
        if ($dto->strategyId !== null) {
            $this->strategies->find($dto->strategyId, $dto->accountId);
        }

        return $this->repository->findByHandle($dto->accountId, $dto->platform, $dto->handle) === null
            ? $this->repository->create($dto)
            : throw CompetitorAlreadyTrackedException::forHandle($dto->platform, $dto->handle);
    }

    public function delete(int $id, int $accountId): bool
    {
        return $this->repository->delete($this->find($id, $accountId));
    }

    /**
     * @return Collection<int, CompetitorPost>|LengthAwarePaginator<int, CompetitorPost>
     */
    public function postsFor(CompetitorPostFilterDTO $filters): Collection|LengthAwarePaginator
    {
        $this->find($filters->competitorId, $filters->accountId);

        return $this->posts->findAll($filters);
    }

    /**
     * Scraping is never synchronous: the caller gets an acknowledgement and keeps reading
     * the rows already in the database until the pipeline replaces them.
     */
    public function requestSync(int $id, int $accountId): Competitor
    {
        $competitor = $this->find($id, $accountId);

        $this->dispatcher->dispatch(self::pipeline($accountId, (int) $competitor->id));

        return $competitor;
    }

    /**
     * The daily pass. An account with no active competitors, no active strategies or the
     * feature switched off costs zero Apify calls and zero tokens.
     */
    public function dispatchDailySync(): int
    {
        return $this->repository->accountIdsWithActiveCompetitors()
            ->filter(fn (int $accountId): bool => $this->isWorthSyncing($accountId))
            ->sum(fn (int $accountId): int => $this->dispatchForAccount($accountId));
    }

    private function dispatchForAccount(int $accountId): int
    {
        return $this->repository->activeForAccount($accountId)
            ->each(fn (Competitor $competitor) => $this->dispatcher->dispatch(
                self::pipeline($accountId, (int) $competitor->id),
            ))
            ->count();
    }

    private function isWorthSyncing(int $accountId): bool
    {
        return $this->settings->get(self::FEATURE_FLAG, $accountId) === true
            && collect($this->strategies->forAccount(new StrategyFilterDTO($accountId, StrategyStatus::Active)))->isNotEmpty();
    }

    /** One chain so scraping, comments and both analyses never race each other. */
    private static function pipeline(int $accountId, int $competitorId): SyncCompetitorJob
    {
        return (new SyncCompetitorJob($accountId, $competitorId))->chain([
            new SyncCompetitorCommentsJob($accountId, $competitorId),
            new AnalyseCompetitorPostsJob($accountId, $competitorId),
            new AnalyseCommentSentimentJob($accountId, $competitorId),
            new MineCommentIdeasJob($accountId, $competitorId),
        ]);
    }
}
