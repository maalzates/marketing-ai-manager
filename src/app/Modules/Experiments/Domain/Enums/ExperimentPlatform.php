<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Domain\Enums;

enum ExperimentPlatform: string
{
    case Instagram = 'instagram';
    case Facebook = 'facebook';
    case Youtube = 'youtube';
    case Tiktok = 'tiktok';
}
