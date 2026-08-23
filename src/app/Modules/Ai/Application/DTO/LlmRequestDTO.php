<?php

declare(strict_types=1);

namespace App\Modules\Ai\Application\DTO;

use App\Modules\Ai\Domain\Contracts\LlmMessageInterface;
use App\Modules\Ai\Domain\Enums\AiTask;

/**
 * A provider-agnostic call, already ordered for prompt caching: `systemPrompt` and
 * `tools` are the stable head every provider matches its cache prefix against, and
 * `messages` holds everything that changes per request.
 */
readonly class LlmRequestDTO
{
    /**
     * @param  list<LlmMessageInterface>  $messages
     * @param  list<array{name: string, description: string, schema: array}>  $tools
     */
    public function __construct(
        public int $accountId,
        public AiTask $task,
        public string $model,
        public string $systemPrompt,
        public array $messages,
        public array $tools,
        public ?array $jsonSchema,
        public int $maxTokens,
        public float $temperature,
    ) {}
}
