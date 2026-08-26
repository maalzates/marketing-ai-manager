<?php

declare(strict_types=1);

namespace App\Modules\Ai\Domain\Enums;

enum LlmProvider: string
{
    case Anthropic = 'anthropic';
    case OpenAi = 'openai';
    case Gemini = 'gemini';

    public function label(): string
    {
        return match ($this) {
            self::Anthropic => 'Anthropic',
            self::OpenAi => 'OpenAI',
            self::Gemini => 'Gemini',
        };
    }

    /** @return array<string, array{input: float, output: float}> */
    public function models(): array
    {
        return (array) config("services.{$this->value}.models", []);
    }

    /**
     * Both tiers are derived from the configured output price rather than named a second
     * time: a price list and a tier list would drift, and the price is already the thing
     * that makes a model cheap or capable.
     */
    public function capableModel(): string
    {
        return $this->byOutputPrice(true);
    }

    public function cheapestModel(): string
    {
        return $this->byOutputPrice(false);
    }

    private function byOutputPrice(bool $highest): string
    {
        $models = collect($this->models())->sortBy(static fn (array $prices): float => (float) $prices['output']);

        return (string) ($highest ? $models->keys()->last() : $models->keys()->first());
    }

    /** @return array{input: float, output: float}|null null for a model this deployment never priced */
    public function priceOf(string $model): ?array
    {
        return $this->models()[$model] ?? null;
    }

    public function pricingUrl(): string
    {
        return (string) config("services.{$this->value}.pricing_url");
    }

    public function baseUrl(): string
    {
        return (string) config("services.{$this->value}.base_url");
    }

    /**
     * The one place the providers' incompatible token arithmetic is settled. Anthropic
     * reports an input count that excludes cache reads; OpenAI and Gemini report one that
     * already contains them. Downstream, `inputTokens` must mean uncached input on all
     * three or the ledger over-bills whichever provider the mapper was not written for.
     */
    public function uncachedInputTokens(int $reportedInputTokens, int $cachedInputTokens): int
    {
        return config("services.{$this->value}.cached_tokens_are_additive") === true
            ? $reportedInputTokens
            : max($reportedInputTokens - $cachedInputTokens, 0);
    }
}
