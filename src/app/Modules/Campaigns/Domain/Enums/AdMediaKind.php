<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Domain\Enums;

enum AdMediaKind: string
{
    case Image = 'image';
    case Video = 'video';
}
