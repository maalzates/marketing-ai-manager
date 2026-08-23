<?php

declare(strict_types=1);

namespace App\Modules\Ai\Application\Services;

use App\Modules\Knowledge\Application\Services\KnowledgeService;

/**
 * Assembles the compact JSON context every prompt carries. It queries nothing: the modules
 * that own strategies, experiments and insights hand their already-loaded summaries in
 * through the with* seams, which is what keeps this module free of their tables.
 */
readonly class PromptContextBuilder
{
    public function __construct(
        private KnowledgeService $knowledge,
        private array $sections = [],
    ) {}

    /** @return array<string, mixed> */
    public function build(int $accountId, ?int $strategyId): array
    {
        return array_filter([
            'domain_knowledge' => $this->knowledge->systemPrompt(),
            'account_id' => $accountId,
            'strategy_id' => $strategyId,
            ...$this->sections,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    public function withStrategy(array $strategy): self
    {
        return $this->with('strategy', $strategy);
    }

    /** @param  list<array<string, mixed>>  $experiments */
    public function withRecentExperiments(array $experiments): self
    {
        return $this->with('recent_experiments', $experiments);
    }

    /** @param  list<array<string, mixed>>  $insights */
    public function withInsights(array $insights): self
    {
        return $this->with('insights', $insights);
    }

    private function with(string $section, array $data): self
    {
        return new self($this->knowledge, [...$this->sections, $section => $data]);
    }
}
