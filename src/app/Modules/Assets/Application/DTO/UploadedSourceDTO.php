<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\DTO;

use App\Modules\Assets\Domain\ValueObjects\MediaFilename;

/**
 * The bytes as PHP already staged them, decoupled from the HTTP upload object so nothing
 * below the door depends on a request.
 */
readonly class UploadedSourceDTO
{
    public function __construct(
        public string $path,
        public string $originalName,
        public string $mimeType,
        public int $sizeBytes,
    ) {}

    public function extension(): string
    {
        return (new MediaFilename($this->originalName, $this->mimeType))->extension();
    }
}
