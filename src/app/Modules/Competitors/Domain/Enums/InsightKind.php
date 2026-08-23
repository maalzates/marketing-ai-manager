<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Domain\Enums;

enum InsightKind: string
{
    case Pattern = 'pattern';
    case ContentIdea = 'content_idea';
    case Sentiment = 'sentiment';
}
