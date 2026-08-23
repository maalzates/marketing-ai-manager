<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\ValueObjects;

use Symfony\Component\Mime\MimeTypes;

/**
 * A filename that still carries its extension. Phone cameras and Drive both produce names
 * without one ("IMG_0042"), and Meta's `/act_{id}/adimages` rejects an upload whose filename has
 * no extension, so the media type is the fallback source of truth.
 */
readonly class MediaFilename
{
    public function __construct(public string $name, public ?string $mimeType) {}

    public function extension(): string
    {
        return strtolower(pathinfo($this->name, PATHINFO_EXTENSION)) ?: $this->fromMimeType();
    }

    public function withExtension(): string
    {
        return pathinfo($this->name, PATHINFO_EXTENSION) !== '' || $this->fromMimeType() === ''
            ? $this->name
            : $this->name.'.'.$this->fromMimeType();
    }

    private function fromMimeType(): string
    {
        return $this->mimeType === null
            ? ''
            : (string) (MimeTypes::getDefault()->getExtensions($this->mimeType)[0] ?? '');
    }
}
