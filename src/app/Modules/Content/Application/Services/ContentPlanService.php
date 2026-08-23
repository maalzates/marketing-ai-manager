<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Services;

use App\Modules\Ai\Application\DTO\AiRequestDTO;
use App\Modules\Ai\Application\Services\AiService;
use App\Modules\Ai\Domain\Enums\AiTask;
use App\Modules\Brands\Application\Services\BrandContextService;
use App\Modules\Competitors\Application\DTO\InsightFilterDTO;
use App\Modules\Competitors\Application\Services\InsightService;
use App\Modules\Competitors\Domain\Enums\InsightStatus;
use App\Modules\Competitors\Infrastructure\Persistence\Insight;
use App\Modules\Content\Application\DTO\ApproveScriptDTO;
use App\Modules\Content\Application\DTO\CreateContentScriptDTO;
use App\Modules\Content\Application\DTO\GenerateScriptsDTO;
use App\Modules\Content\Domain\Contracts\ContentScriptRepositoryInterface;
use App\Modules\Content\Domain\Enums\ContentFormat;
use App\Modules\Content\Domain\Enums\ScriptStatus;
use App\Modules\Content\Domain\Exceptions\ContentScriptNotFoundException;
use App\Modules\Content\Domain\Exceptions\ScriptAlreadyApprovedException;
use App\Modules\Content\Domain\Exceptions\ScriptRejectedException;
use App\Modules\Content\Infrastructure\Persistence\ContentScript;
use App\Modules\Experiments\Application\DTO\CreateExperimentDTO;
use App\Modules\Experiments\Application\DTO\ExperimentFilterDTO;
use App\Modules\Experiments\Application\DTO\UpdateExperimentDTO;
use App\Modules\Experiments\Application\Services\ExperimentService;
use App\Modules\Experiments\Domain\Enums\ExperimentStatus;
use App\Modules\Experiments\Domain\Enums\ExperimentType;
use App\Modules\Experiments\Domain\Enums\ProductionStatus;
use App\Modules\Experiments\Infrastructure\Persistence\Experiment;
use App\Modules\Strategies\Application\Services\StrategyService;
use App\Modules\Strategies\Infrastructure\Persistence\Strategy;
use Illuminate\Support\Collection;

/**
 * Writes the organic plan: the model turns competitor insights, mined comment ideas and the
 * account's own verdict history into full scripts, and an approved script becomes the
 * experiment it will be judged by.
 *
 * The model writes text and decides; it never produces the audiovisual piece. `required_assets`
 * is a shopping list for the human who records it.
 */
readonly class ContentPlanService
{
    private const int HISTORY_LIMIT = 15;

    private const int INSIGHT_LIMIT = 25;

    private const array OUTPUT_SCHEMA = [
        'type' => 'object',
        'properties' => ['scripts' => ['type' => 'array']],
        'required' => ['scripts'],
    ];

    public function __construct(
        private ContentScriptRepositoryInterface $repository,
        private AiService $ai,
        private InsightService $insights,
        private StrategyService $strategies,
        private BrandContextService $brand,
        private ExperimentService $experiments,
    ) {}

    /**
     * @return Collection<int, ContentScript>
     */
    public function generate(GenerateScriptsDTO $dto): Collection
    {
        $strategy = $this->strategies->find($dto->strategyId, $dto->accountId);
        $insights = $this->candidateInsights($dto->accountId, $dto->strategyId);

        $scripts = collect($this->ai->structured(new AiRequestDTO(
            $dto->accountId,
            AiTask::ContentScript,
            $this->prompt($dto),
            $this->context($dto, $strategy, $insights),
            userId: $dto->userId,
            strategyId: $dto->strategyId,
        ), self::OUTPUT_SCHEMA)['scripts'])
            ->take($dto->count)
            ->map(fn (array $script): ContentScript => $this->persist($dto, $script, $insights))
            ->values();

        $this->consumeInsights($dto->accountId, $scripts);

        return $scripts;
    }

    /**
     * An insight that already produced a script stops being a candidate, so the next batch
     * mines new ground instead of rewriting the same idea.
     *
     * @param  Collection<int, ContentScript>  $scripts
     */
    private function consumeInsights(int $accountId, Collection $scripts): void
    {
        $scripts->flatMap(fn (ContentScript $script): array => $script->source_insight_ids)
            ->unique()
            ->each(fn (int $insightId) => $this->insights->markUsed($insightId, $accountId));
    }

    public function approve(ApproveScriptDTO $dto): ContentScript
    {
        $script = $this->repository->findById($dto->scriptId, $dto->accountId)
            ?? throw ContentScriptNotFoundException::withId($dto->scriptId);

        $this->assertApprovable($script);

        return $this->repository->approve($script, (int) $this->openProduction(
            $this->experiments->create($this->experimentFor($script, $dto)),
            $dto->accountId,
        )->id);
    }

    private function assertApprovable(ContentScript $script): void
    {
        match ($script->status) {
            ScriptStatus::Approved => throw ScriptAlreadyApprovedException::withId($script->id, $script->experiment_id),
            ScriptStatus::Rejected => throw ScriptRejectedException::withId($script->id),
            ScriptStatus::Draft => null,
        };
    }

    private function experimentFor(ContentScript $script, ApproveScriptDTO $dto): CreateExperimentDTO
    {
        return new CreateExperimentDTO(
            $dto->accountId,
            (int) $script->strategy_id,
            ExperimentType::Organic,
            $dto->platform,
            $script->title,
            $dto->hypothesis,
            $dto->expectedResult,
            $dto->startsAt,
            $dto->endsAt,
            null,
            ['content_script_id' => $script->id, 'format' => $script->format->value],
            ExperimentStatus::Scheduled,
        );
    }

    /** Production starts the moment the script is approved: there is a piece to record. */
    private function openProduction(Experiment $experiment, int $accountId): Experiment
    {
        return $this->experiments->update(new UpdateExperimentDTO(
            $accountId,
            (int) $experiment->id,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            ProductionStatus::Script,
        ));
    }

    /**
     * @param  Collection<int, Insight>  $insights
     */
    private function persist(GenerateScriptsDTO $dto, array $script, Collection $insights): ContentScript
    {
        return $this->repository->create(new CreateContentScriptDTO(
            $dto->accountId,
            $dto->strategyId,
            (string) ($script['title'] ?? 'Untitled script'),
            (string) ($script['hook'] ?? ''),
            (array) ($script['structure'] ?? []),
            (string) ($script['cta'] ?? ''),
            ContentFormat::tryFrom((string) ($script['format'] ?? '')) ?? ContentFormat::Reel,
            (array) ($script['required_assets'] ?? []),
            $this->citedInsightIds($script, $insights),
        ));
    }

    /**
     * Only ids the model was actually shown are stored: a hallucinated reference would
     * otherwise become evidence nobody can trace.
     *
     * @param  Collection<int, Insight>  $insights
     * @return list<int>
     */
    private function citedInsightIds(array $script, Collection $insights): array
    {
        return collect($script['source_insight_ids'] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->intersect($insights->map(fn (Insight $insight): int => (int) $insight->id))
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Insight>
     */
    private function candidateInsights(int $accountId, int $strategyId): Collection
    {
        return $this->insights
            ->forAccount(new InsightFilterDTO($accountId, status: InsightStatus::New, strategyId: $strategyId))
            ->take(self::INSIGHT_LIMIT);
    }

    /**
     * @param  Collection<int, Insight>  $insights
     * @return array<string, mixed>
     */
    private function context(GenerateScriptsDTO $dto, Strategy $strategy, Collection $insights): array
    {
        return [
            'brand' => $this->brand->promptContext($dto->accountId, (int) $strategy->brand_profile_id),
            'strategy' => [
                'name' => $strategy->name,
                'objective' => $strategy->objective,
                'north_star_metric' => $strategy->north_star_metric,
                'constraints' => $strategy->constraints,
                'organic_cadence' => $strategy->organic_cadence,
            ],
            'insights' => $insights->map(fn (Insight $insight): array => [
                'id' => (int) $insight->id,
                'kind' => $insight->kind->value,
                'source' => $insight->source->value,
                'title' => $insight->title,
                'body' => $insight->body,
                'evidence' => $insight->evidence,
                'score' => (float) $insight->score,
            ])->values()->all(),
            'verdict_history' => $this->verdictHistory($dto->accountId, $dto->strategyId),
            'recent_scripts' => $this->repository
                ->recentTitles($dto->accountId, $dto->strategyId, self::HISTORY_LIMIT)
                ->map(fn (ContentScript $script): array => [
                    'title' => $script->title,
                    'hook' => $script->hook,
                    'format' => $script->format->value,
                    'status' => $script->status->value,
                ])->values()->all(),
            'requested_formats' => collect($dto->formats)
                ->map(fn (ContentFormat $format): string => $format->value)
                ->all(),
            'brief' => $dto->brief,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function verdictHistory(int $accountId, int $strategyId): array
    {
        return $this->experiments
            ->forStrategy(new ExperimentFilterDTO($accountId, $strategyId, null, null, null, 0, 1))
            ->take(self::HISTORY_LIMIT)
            ->map(fn (Experiment $experiment): array => [
                'code' => $experiment->code,
                'title' => $experiment->title,
                'hypothesis' => $experiment->hypothesis,
                'verdict' => $experiment->verdict?->value,
                'verdict_reason' => $experiment->verdict_reason,
            ])
            ->values()
            ->all();
    }

    private function prompt(GenerateScriptsDTO $dto): string
    {
        return <<<PROMPT
        Write {$dto->count} organic content scripts for this strategy.

        Return JSON: {"scripts":[{"title","hook","structure":[{"beat","detail"}],"cta",
        "format":"reel|carousel|story|photo|video","required_assets":[{"type","aspect_ratio",
        "duration_seconds","quantity"}],"source_insight_ids":[]}]}.

        Rules: every script must cite the insight ids it comes from; do not repeat a hypothesis
        the verdict history already refuted; required_assets describes what a human has to
        record, never anything you generate.
        PROMPT;
    }
}
