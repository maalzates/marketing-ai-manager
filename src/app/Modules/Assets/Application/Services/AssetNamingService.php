<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\Services;

use App\Modules\Assets\Domain\Enums\AssetType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * `{experimento}_{fecha}_{tipo}_{slug-del-tema}_{versión|orden}` — written for a human browsing
 * Drive. Nothing in this application ever reads meaning back out of a filename: the only
 * identifier the system trusts is `drive_file_id`.
 */
readonly class AssetNamingService
{
    private const string DATE_FORMAT = 'Y-m-d';

    public function forSingle(string $experimentCode, AssetType $type, string $topic, int $version, string $extension): string
    {
        return $this->compose($experimentCode, $type->value, $topic, 'v'.$version, $extension);
    }

    /** A slide's order is recorded in the database; the number here is only for the human reading it. */
    public function forSlide(string $experimentCode, string $topic, int $position, string $extension): string
    {
        return $this->compose($experimentCode, AssetType::Carousel->value, $topic, sprintf('%02d', $position), $extension);
    }

    private function compose(string $experimentCode, string $type, string $topic, string $suffix, string $extension): string
    {
        return sprintf(
            '%s_%s_%s_%s_%s%s',
            $experimentCode,
            CarbonImmutable::now()->format(self::DATE_FORMAT),
            $type,
            Str::slug($topic),
            $suffix,
            $extension === '' ? '' : '.'.$extension,
        );
    }
}
