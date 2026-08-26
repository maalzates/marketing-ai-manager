<?php

declare(strict_types=1);

namespace App\Modules\Ai\Application\Services;

use App\Modules\Ai\Domain\Enums\LlmProvider;

readonly class CostCalculator
{
    private const int TOKENS_PER_PRICED_UNIT = 1_000_000;

    /** All three providers bill a cache read at a tenth of their base input rate. */
    private const float CACHED_INPUT_RATE_MULTIPLIER = 0.1;

    /** Reasoning tokens are invisible in the answer but billed at the full output rate. */
    public function estimate(
        string $model,
        int $inputTokens,
        int $outputTokens,
        int $cachedInputTokens,
        int $reasoningTokens = 0,
    ): float {
        $prices = self::priceOf($model);

        // A model the provider lists and `config/services.php` does not price is callable on
        // purpose: prices live on a web page and this table trails it. The call is recorded at
        // zero rather than refused, and Settings → Models links out to the provider's pricing
        // so the gap is fillable instead of invisible.
        if ($prices === null) {
            return 0.0;
        }

        return (
            $inputTokens * $prices['input']
            + $cachedInputTokens * $prices['input'] * self::CACHED_INPUT_RATE_MULTIPLIER
            + ($outputTokens + $reasoningTokens) * $prices['output']
        ) / self::TOKENS_PER_PRICED_UNIT;
    }

    /**
     * Searched across every provider rather than resolved through one: pricing does not need
     * to know who serves the model, and a model id belongs to exactly one of the three.
     *
     * @return array{input: float, output: float}|null
     */
    private static function priceOf(string $model): ?array
    {
        return collect(LlmProvider::cases())
            ->map(static fn (LlmProvider $provider): ?array => $provider->priceOf($model))
            ->filter()
            ->first();
    }
}
