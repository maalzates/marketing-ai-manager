<?php

declare(strict_types=1);

namespace App\Modules\Ai\Application\DTO\Messages;

use App\Modules\Ai\Domain\Contracts\LlmMessageInterface;
use App\Modules\Ai\Domain\Enums\MessageRole;

readonly class TextMessage implements LlmMessageInterface
{
    public function __construct(public MessageRole $role, public string $text) {}
}
