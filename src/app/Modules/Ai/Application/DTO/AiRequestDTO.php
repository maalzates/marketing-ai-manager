<?php

declare(strict_types=1);

namespace App\Modules\Ai\Application\DTO;

use App\Modules\Ai\Domain\Contracts\LlmMessageInterface;
use App\Modules\Ai\Domain\Enums\AiTask;

/**
 * What every other module hands to AiService. It carries intent and already-loaded
 * context, never a model id or a provider: choosing those is the Ai module's job.
 *
 * An empty `prompt` means `history` already ends with the turn to send, which is how a
 * tool loop continues without inventing a user message.
 */
readonly class AiRequestDTO
{
    /**
     * @param  array<string, mixed>  $context
     * @param  list<array{name: string, description: string, schema: array}>  $tools
     * @param  list<LlmMessageInterface>  $history
     */
    public function __construct(
        public int $accountId,
        public AiTask $task,
        public string $prompt,
        public array $context = [],
        public array $tools = [],
        public array $history = [],
        public ?int $userId = null,
        public ?int $strategyId = null,
        public ?int $maxTokens = null,
        public ?float $temperature = null,
    ) {}
}
