<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Domain\ValueObjects;

readonly class CreativeSpec
{
    /**
     * @param  list<AdMedia>  $media
     */
    public function __construct(
        public string $name,
        public string $pageId,
        public ?string $instagramUserId,
        public string $message,
        public ?string $headline,
        public ?string $link,
        public ?string $callToAction,
        public array $media,
        public bool $automaticEnhancements = false,
    ) {}
}
