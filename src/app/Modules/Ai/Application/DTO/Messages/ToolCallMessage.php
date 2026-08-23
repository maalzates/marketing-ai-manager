<?php

declare(strict_types=1);

namespace App\Modules\Ai\Application\DTO\Messages;

use App\Modules\Ai\Domain\Contracts\LlmMessageInterface;

/**
 * The assistant turn that asked for tools. `calls` takes `LlmResponseDTO::$toolCalls`
 * unchanged, so echoing the previous turn back is a pass-through rather than a remap.
 */
readonly class ToolCallMessage implements LlmMessageInterface
{
    /** @param  list<array{id: string, name: string, input: array}>  $calls */
    public function __construct(public ?string $text, public array $calls) {}
}
