<?php

declare(strict_types=1);

namespace App\Modules\Ai\Application\Services;

readonly class CostCalculator
{
    private const int TOKENS_PER_PRICED_UNIT = 1_000_000;

    /** All three providers bill a cache read at a tenth of their base input rate. */
    private const float CACHED_INPUT_RATE_MULTIPLIER = 0.1;

    public function __construct(private ModelRouter $router) {}

    /** Reasoning tokens are invisible in the answer but billed at the full output rate. */
    public function estimate(
        string $model,
        int $inputTokens,
        int $outputTokens,
        int $cachedInputTokens,
        int $reasoningTokens = 0,
    ): float {
        $prices = $this->router->providerFor($model)->models()[$model];

        return (
            $inputTokens * $prices['input']
            + $cachedInputTokens * $prices['input'] * self::CACHED_INPUT_RATE_MULTIPLIER
            + ($outputTokens + $reasoningTokens) * $prices['output']
        ) / self::TOKENS_PER_PRICED_UNIT;
    }
}
