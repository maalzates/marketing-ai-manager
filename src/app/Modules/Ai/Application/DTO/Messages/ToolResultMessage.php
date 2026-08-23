<?php

declare(strict_types=1);

namespace App\Modules\Ai\Application\DTO\Messages;

use App\Modules\Ai\Domain\Contracts\LlmMessageInterface;

/**
 * Results going back to the model. Each result carries both `id` and `name` because the
 * providers disagree on which one addresses a call: Anthropic and OpenAI match on the id,
 * Gemini's `functionResponse` matches on the name. Dropping either makes one provider
 * untranslatable.
 */
readonly class ToolResultMessage implements LlmMessageInterface
{
    /** @param  list<array{id: string, name: string, content: string, is_error?: bool}>  $results */
    public function __construct(public array $results) {}
}
