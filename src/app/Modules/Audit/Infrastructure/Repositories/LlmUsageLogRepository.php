<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Repositories;

use App\Modules\Audit\Application\DTO\RecordLlmUsageDTO;
use App\Modules\Audit\Application\DTO\UsageFilterDTO;
use App\Modules\Audit\Domain\Contracts\LlmUsageLogRepositoryInterface;
use App\Modules\Audit\Domain\Enums\UsageGrouping;
use App\Modules\Audit\Domain\Exceptions\UsageLogWriteFailedException;
use App\Modules\Audit\Infrastructure\Persistence\LlmUsageLog;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Throwable;

readonly class LlmUsageLogRepository implements LlmUsageLogRepositoryInterface
{
    /**
     * Hidden reasoning is billed as output but never shows up in the text, so it counts
     * towards the budget. Providers disagree on whether input_tokens already includes the
     * cached ones, so the Ai clients normalise that before it reaches this table.
     */
    private const string BILLED_TOKENS = 'input_tokens + output_tokens + reasoning_tokens';

    public function __construct(private LlmUsageLog $model) {}

    public function create(RecordLlmUsageDTO $dto): LlmUsageLog
    {
        try {
            return $this->model->newQuery()->create([
                'account_id' => $dto->accountId,
                'user_id' => $dto->userId,
                'feature' => $dto->feature,
                'provider' => $dto->provider,
                'model' => $dto->model,
                'input_tokens' => $dto->inputTokens,
                'output_tokens' => $dto->outputTokens,
                'reasoning_tokens' => $dto->reasoningTokens,
                'cached_input_tokens' => $dto->cachedInputTokens,
                'estimated_cost_usd' => $dto->estimatedCostUsd,
            ]);
        } catch (Throwable $exception) {
            throw UsageLogWriteFailedException::wrap($exception, context: [
                'account_id' => $dto->accountId,
                'feature' => $dto->feature,
                'provider' => $dto->provider,
            ]);
        }
    }

    public function summary(UsageFilterDTO $filters): Collection
    {
        return $this->query($filters)
            ->selectRaw($this->label($filters).' as label')
            ->selectRaw('COUNT(*) as calls')
            ->selectRaw('SUM(input_tokens) as input_tokens')
            ->selectRaw('SUM(output_tokens) as output_tokens')
            ->selectRaw('SUM(reasoning_tokens) as reasoning_tokens')
            ->selectRaw('SUM(cached_input_tokens) as cached_input_tokens')
            ->selectRaw('SUM('.self::BILLED_TOKENS.') as total_tokens')
            ->selectRaw('SUM(estimated_cost_usd) as cost_usd')
            ->groupBy('label')
            ->orderBy('label')
            ->get()
            ->map(fn (object $row): array => [
                'label' => (string) $row->label,
                'calls' => (int) $row->calls,
                'input_tokens' => (int) $row->input_tokens,
                'output_tokens' => (int) $row->output_tokens,
                'reasoning_tokens' => (int) $row->reasoning_tokens,
                'cached_input_tokens' => (int) $row->cached_input_tokens,
                'total_tokens' => (int) $row->total_tokens,
                'cost_usd' => (float) $row->cost_usd,
            ]);
    }

    public function totalTokensBetween(int $accountId, CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) $this->model->newQuery()
            ->where('account_id', $accountId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('COALESCE(SUM('.self::BILLED_TOKENS.'), 0) as total_tokens')
            ->value('total_tokens');
    }

    private function label(UsageFilterDTO $filters): string
    {
        return match ($filters->groupBy) {
            UsageGrouping::FEATURE => 'feature',
            UsageGrouping::ACCOUNT => 'account_id',
            UsageGrouping::DAY => 'DATE(created_at)',
        };
    }

    private function query(UsageFilterDTO $filters): Builder
    {
        return $this->model->newQuery()
            ->when(
                $filters->accountId !== null,
                fn (EloquentBuilder $query) => $query->where('account_id', $filters->accountId),
            )
            ->whereBetween('created_at', [$filters->from, $filters->to])
            ->toBase();
    }
}
