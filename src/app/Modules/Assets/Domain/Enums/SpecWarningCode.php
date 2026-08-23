<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Enums;

enum SpecWarningCode: string
{
    case AspectRatioNotVertical = 'aspect_ratio_not_vertical';
    case AspectRatioNotFeed = 'aspect_ratio_not_feed';
    case ImageTooHeavy = 'image_too_heavy';
    case VideoTooHeavy = 'video_too_heavy';
    case VideoTooLong = 'video_too_long';
    case UnexpectedMimeType = 'unexpected_mime_type';

    public function message(): string
    {
        return match ($this) {
            self::AspectRatioNotVertical => 'Reels and stories are expected to be 9:16.',
            self::AspectRatioNotFeed => 'Feed pieces are expected to be 1:1 or 4:5.',
            self::ImageTooHeavy => 'Images above 8 MB are rejected by Meta.',
            self::VideoTooHeavy => 'Reels above 300 MB are rejected by Instagram.',
            self::VideoTooLong => 'Reels longer than 15 minutes are rejected by Instagram.',
            self::UnexpectedMimeType => 'The file type does not match the declared asset type.',
        };
    }
}
