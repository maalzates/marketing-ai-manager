<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Enums;

enum ContentFormat: string
{
    case Reel = 'reel';
    case Carousel = 'carousel';
    case Story = 'story';
    case Photo = 'photo';
    case Video = 'video';

    public function isVideo(): bool
    {
        return $this === self::Reel || $this === self::Video || $this === self::Story;
    }

    public function isCarousel(): bool
    {
        return $this === self::Carousel;
    }
}
