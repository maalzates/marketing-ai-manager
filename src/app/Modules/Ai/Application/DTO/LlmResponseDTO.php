<?php

declare(strict_types=1);

namespace App\Modules\Ai\Application\DTO;

use App\Modules\Ai\Domain\Enums\LlmProvider;

/**
 * The three providers count tokens differently — Anthropic excludes cached tokens from
 * its input count, OpenAI and Gemini include them. Each adapter normalises to the same
 * contract here: `inputTokens` is the *uncached* input, `cachedInputTokens` the rest.
 * Anything else double-bills or under-bills the account's ledger.
 *
 * `reasoningTokens` is normalised the same way: it is hidden output, billed at the output
 * rate but absent from the text, and it never overlaps `outputTokens`. The two always add
 * up to the total output, which is what lets the ledger sum the three counters blindly.
 */
readonly class LlmResponseDTO
{
    /** @param  list<array{id: string, name: string, input: array}>  $toolCalls */
    public function __construct(
        public ?string $text,
        public ?array $structured,
        public array $toolCalls,
        public string $stopReason,
        public int $inputTokens,
        public int $outputTokens,
        public int $cachedInputTokens,
        public int $reasoningTokens,
        public string $model,
        public LlmProvider $provider,
        public array $raw,
    ) {}
}
