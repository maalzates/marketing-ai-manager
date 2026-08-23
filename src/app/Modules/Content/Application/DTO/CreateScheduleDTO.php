<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\DTO;

use Carbon\CarbonImmutable;

/**
 * No platform: a slot goes out on the channel its experiment declares. Letting the caller name
 * one would allow a YouTube slot on an Instagram experiment, and the metrics import would then
 * read the wrong channel and attribute the result to the wrong hypothesis.
 */
readonly class CreateScheduleDTO
{
    public function __construct(
        public int $accountId,
        public int $experimentId,
        public ?int $assetId,
        public CarbonImmutable $scheduledAt,
    ) {}
}
