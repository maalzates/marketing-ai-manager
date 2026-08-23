<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Domain\Enums;

enum CompetitorPlatform: string
{
    case Instagram = 'instagram';
    case FacebookAds = 'facebook_ads';
    case Youtube = 'youtube';
    case Tiktok = 'tiktok';
}
