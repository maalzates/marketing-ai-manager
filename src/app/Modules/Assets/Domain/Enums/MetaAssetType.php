<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Enums;

enum MetaAssetType: string
{
    case ImageHash = 'image_hash';
    case VideoId = 'video_id';
}
