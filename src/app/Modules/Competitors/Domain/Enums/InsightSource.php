<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Domain\Enums;

enum InsightSource: string
{
    case CompetitorAnalysis = 'competitor_analysis';
    case CommentMining = 'comment_mining';
    case OwnContent = 'own_content';
}
