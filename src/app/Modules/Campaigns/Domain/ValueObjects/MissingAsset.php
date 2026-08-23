<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Domain\ValueObjects;

/**
 * What a proposal is still waiting for. Format, aspect ratio and duration are what the
 * user has to go and produce, so they travel with the gap instead of only the id.
 */
readonly class MissingAsset
{
    public function __construct(
        public int $assetId,
        public ?string $reason,
        public ?string $format = null,
        public ?string $aspectRatio = null,
        public ?int $durationSeconds = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'asset_id' => $this->assetId,
            'reason' => $this->reason,
            'format' => $this->format,
            'aspect_ratio' => $this->aspectRatio,
            'duration_seconds' => $this->durationSeconds,
        ];
    }
}
