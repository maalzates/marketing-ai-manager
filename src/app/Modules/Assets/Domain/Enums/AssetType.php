<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Enums;

enum AssetType: string
{
    case Photo = 'photo';
    case VideoVertical = 'video_vertical';
    case Reel = 'reel';
    case Carousel = 'carousel';
    case CarouselSlide = 'carousel_slide';
    case Story = 'story';

    /** A carousel parent holds no bytes of its own: its slides are the pieces that reach Drive. */
    public function holdsDriveFile(): bool
    {
        return $this !== self::Carousel;
    }

    public function isVideo(): bool
    {
        return in_array($this, [self::VideoVertical, self::Reel], true);
    }

    /** The placements this project publishes to are all vertical except the feed image formats. */
    public function isVerticalPlacement(): bool
    {
        return in_array($this, [self::VideoVertical, self::Reel, self::Story], true);
    }

    public function isFeedPlacement(): bool
    {
        return in_array($this, [self::Photo, self::CarouselSlide], true);
    }
}
