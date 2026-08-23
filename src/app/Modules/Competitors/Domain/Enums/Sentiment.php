<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Domain\Enums;

enum Sentiment: string
{
    case Positive = 'positive';
    case Negative = 'negative';
    case Neutral = 'neutral';
}
