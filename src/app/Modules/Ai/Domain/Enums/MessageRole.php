<?php

declare(strict_types=1);

namespace App\Modules\Ai\Domain\Enums;

enum MessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
}
