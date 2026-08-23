<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\DTO;

/**
 * What a channel will accept for one format. Declared by the channel and enforced above it,
 * so a piece that cannot possibly be published is rejected before a container is created —
 * an out-of-spec file otherwise costs one of the 400 daily container creations and only
 * fails minutes later, while polling.
 */
readonly class ChannelMediaSpecDTO
{
    /**
     * @param  list<string>  $imageMimeTypes
     * @param  list<string>  $videoMimeTypes
     */
    public function __construct(
        public array $imageMimeTypes,
        public int $maxImageBytes,
        public array $videoMimeTypes,
        public int $maxVideoBytes,
        public int $minVideoSeconds,
        public int $maxVideoSeconds,
    ) {}
}
