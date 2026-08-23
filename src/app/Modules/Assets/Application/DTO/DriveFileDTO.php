<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\DTO;

use App\Modules\Assets\Domain\ValueObjects\AspectRatio;
use Illuminate\Support\Arr;

readonly class DriveFileDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $mimeType,
        public ?int $sizeBytes,
        public ?AspectRatio $aspectRatio,
        public ?int $durationSeconds,
        public bool $trashed,
        public ?string $folderId,
    ) {}

    public static function fromResponse(array $file): self
    {
        return new self(
            (string) Arr::get($file, 'id'),
            (string) Arr::get($file, 'name'),
            Arr::get($file, 'mimeType'),
            // Drive serialises `size` as a string (int64), and it is absent for folders.
            Arr::has($file, 'size') ? (int) Arr::get($file, 'size') : null,
            AspectRatio::fromDimensions(
                (int) Arr::get($file, 'videoMediaMetadata.width', Arr::get($file, 'imageMediaMetadata.width', 0)),
                (int) Arr::get($file, 'videoMediaMetadata.height', Arr::get($file, 'imageMediaMetadata.height', 0)),
            ),
            Arr::has($file, 'videoMediaMetadata.durationMillis')
                ? (int) round(((int) Arr::get($file, 'videoMediaMetadata.durationMillis')) / 1000)
                : null,
            (bool) Arr::get($file, 'trashed', false),
            Arr::get($file, 'parents.0'),
        );
    }
}
